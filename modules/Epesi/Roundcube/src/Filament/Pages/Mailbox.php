<?php

namespace Epesi\Modules\Roundcube\Filament\Pages;

use App\Filament\Actions\InstallRoundcubeAction;
use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\User;
use App\Support\UiState;
use BackedEnum;
use Epesi\Modules\Mail\Filament\Actions\LinkRecordAction;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\MailAccountResource;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\MailFetcher;
use Epesi\Modules\Mail\Services\UnknownSenders;
use Epesi\Modules\Mail\Support\Records;
use Epesi\Modules\OAuth\Exceptions\OAuthException;
use Epesi\Modules\Roundcube\Filament\UnknownSenderRow;
use Epesi\Modules\Roundcube\Roundcube;
use Epesi\Modules\Roundcube\Services\RoundcubeInstaller;
use Epesi\Modules\Roundcube\Services\TicketIssuer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Throwable;
use UnitEnum;

/**
 * Your mailbox in the Roundcube webmail — Epesi's CRM_Roundcube screen. The
 * frame logs in with a one-time ticket (TicketIssuer) and talks back through
 * postMessage: `archive` when its Archive button asks for messages to be
 * archived (this page archives them, moves them to the archive folder and
 * posts `refresh` back), `archived` when a sent copy was saved into the
 * archive folder, `login-required` when its session ran out.
 */
class Mailbox extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Mailbox';

    protected static ?string $title = 'Mailbox';

    // Just above E-mails, the archive.
    protected static ?int $navigationSort = 59;

    protected string $view = 'epesi-roundcube::mailbox';

    #[Locked]
    public ?int $accountId = null;

    #[Locked]
    public ?string $frameUrl = null;

    /** Why an OAuth account's frame can't log in (not connected, refused), shown instead of it. */
    #[Locked]
    public ?string $oauthError = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole(User::CRM_ROLES) ?? false;
    }

    public function mount(): void
    {
        // The account in the address, else the one used last (kept past logout).
        $requested = request()->integer('account') ?: (int) UiState::recall('mailbox.account');
        $account = ($requested ? $this->accountQuery()->find($requested) : null) ?? $this->accounts()->first();

        $this->accountId = $account?->getKey();
        UiState::remember('mailbox.account', $this->accountId);
        $this->refreshPlugins();
        $this->frameUrl = $account && $this->isInstalled() ? $this->ticketUrl($account) : null;
    }

    public function isInstalled(): bool
    {
        return Roundcube::isInstalled();
    }

    /** Installed, but the web server can't read the files (see Roundcube::isUnreadable()). */
    public function isUnreadable(): bool
    {
        return Roundcube::isUnreadable();
    }

    /**
     * The epesi_* plugins Roundcube runs are copies of the module's: a module
     * update or a deploy changes the module's without running the installer,
     * so the copies are brought up to date before Roundcube opens.
     */
    protected function refreshPlugins(): void
    {
        if (! Roundcube::isDownloaded()) {
            return;
        }

        try {
            app(RoundcubeInstaller::class)->refreshPlugins();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Shown in place of the mail client until Roundcube is downloaded. */
    public function installRoundcubeAction(): Action
    {
        return InstallRoundcubeAction::make('installRoundcube');
    }

    /**
     * @return Collection<int, MailAccount>
     */
    public function accounts(): Collection
    {
        return $this->accountQuery()->orderByDesc('is_default')->orderBy('name')->get();
    }

    public function account(): ?MailAccount
    {
        return $this->accountId ? $this->accountQuery()->find($this->accountId) : null;
    }

    public function accountsUrl(): string
    {
        return MailAccountResource::getUrl('index', panel: 'user-settings');
    }

    public function switchAccount(int $id): void
    {
        $account = $this->accountQuery()->find($id);

        if ($account) {
            $this->accountId = $account->getKey();
            UiState::remember('mailbox.account', $this->accountId);

            $this->load($account);

            if ($this->showsOAuthError($account)) {
                return;
            }

            // getHeaderActions() was already cached once, from $this->accountId
            // as it was before this method ran — Livewire's bootedInteractsWithActions()
            // caches header actions before dispatching the action call that runs
            // this closure. Left alone, the account selector's label and checkmark
            // would render one switch behind the account actually loaded below.
            $this->cachedHeaderActions = [];
            $this->cacheInteractsWithHeaderActions();
        }
    }

    /** The folder the account's mail client was last left on, to open it there again. */
    public function rememberedFolder(): ?string
    {
        return $this->accountId ? UiState::recall('mailbox.folder.'.$this->accountId) : null;
    }

    /** The frame's folder changed: kept per account, so the next visit (or login) opens it. */
    public function rememberFolder(string $folder): void
    {
        if ($this->accountId && $folder !== '' && mb_strlen($folder) <= 255) {
            UiState::remember('mailbox.folder.'.$this->accountId, $folder);
        }
    }

    /** The frame's session is gone: log it in again. */
    public function relogin(): void
    {
        if ($account = $this->account()) {
            $this->load($account);
            $this->showsOAuthError($account);
        }
    }

    /**
     * The frame (wire:ignore) can't show why an OAuth account didn't get a
     * ticket: the page is rendered again, with the message.
     */
    protected function showsOAuthError(MailAccount $account): bool
    {
        if ($this->oauthError === null) {
            return false;
        }

        $this->redirect(static::getUrl(['account' => $account->getKey()]));

        return true;
    }

    /**
     * The frame's Archive button, after its checks: archive these messages
     * from the folder the user is in, then move them to the archive folder.
     * Returns whether any left the folder, so the frame refreshes its list.
     *
     * When the other party of a message is in no contact or company
     * ($parties: each message's From and To, from the plugin), the "Sender not
     * in Contacts" dialog opens first and archives once confirmed; this
     * returns false so the frame stops waiting, and the dialog refreshes it.
     *
     * @param  array<int, int>  $uids
     * @param  array<int, array{from?: mixed, to?: mixed}>  $parties
     */
    public function archive(string $folder, array $uids, array $parties = []): bool
    {
        $account = $this->account();

        if (! $account || $folder === '' || mb_strlen($folder) > 255) {
            return false;
        }

        $unknown = app(UnknownSenders::class)->find($parties, Auth::user(), $account);

        if ($unknown !== []) {
            $this->mountAction('unknownSenders', [
                'folder' => $folder,
                'uids' => array_values(array_slice(array_map('intval', $uids), 0, MailFetcher::MAX_PER_CLICK)),
                'unknown' => $unknown,
                'outgoing' => $this->allOutgoing($parties, $account),
            ]);

            return false;
        }

        return $this->archiveNow($folder, $uids);
    }

    /**
     * Archive, then move: the Archive button's work once nothing needs asking.
     * With $links (from the unknown-sender dialog) the messages are linked to
     * those records too, and the result is a notification rather than the
     * dialog after archiving, which that dialog already was.
     *
     * @param  array<int, int>  $uids
     * @param  Collection<int, Model>|null  $links
     */
    protected function archiveNow(string $folder, array $uids, ?Collection $links = null): bool
    {
        $account = $this->account();

        if (! $account) {
            return false;
        }

        try {
            $result = app(MailFetcher::class)->archiveFrom($account, $folder, $uids);
        } catch (Throwable $e) {
            Notification::make()->title(__('The message was not archived'))->body($e->getMessage())->danger()->persistent()->send();

            return false;
        }

        if ($result['mails']->isEmpty()) {
            $this->notArchived();

            return false;
        }

        if ($links === null) {
            $this->afterArchiving($result['mails'], $account, $result['moveError']);
        } else {
            $this->linkMails($result['mails'], $links);
            $this->archivedNotification($result['mails'], $account, $result['moveError']);
        }

        return $result['moveError'] === null;
    }

    /**
     * Roundcube saved a sent copy into the archive folder ("Archive this
     * message after sending"): archive it from there.
     *
     * @param  array<int, string>  $messageIds
     */
    public function archived(array $messageIds = []): void
    {
        $account = $this->account();

        if (! $account || $messageIds === []) {
            return;
        }

        try {
            $mails = app(MailFetcher::class)->archiveFiled($account, array_slice($messageIds, 0, 50));
        } catch (Throwable $e) {
            Notification::make()->title(__('The message was not archived'))->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        if ($mails->isEmpty()) {
            $this->notArchived();

            return;
        }

        // Already archived, so the unknown recipients are asked about after:
        // an address added then still links the message (relinkExisting()).
        $unknown = app(UnknownSenders::class)->find($this->partiesOf($mails), Auth::user(), $account, outgoing: true);

        if ($unknown !== []) {
            $this->mountAction('unknownSenders', [
                'mails' => $mails->modelKeys(),
                'unknown' => $unknown,
                'outgoing' => true,
                'sent' => true,
            ]);

            return;
        }

        // The same dialog as the Archive button's, to link the sent message to records.
        $this->afterArchiving($mails, $account, sent: true);
    }

    /**
     * Archived messages' From and To in the shape the plugin sends.
     *
     * @param  Collection<int, Mail>  $mails
     * @return list<array{from: list<array{email: string, name: string}>, to: list<array{email: string, name: string}>}>
     */
    protected function partiesOf(Collection $mails): array
    {
        $parse = function (?string $header): array {
            preg_match_all('/(?:"?([^",<]*?)"?\s*<)?([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})>?/i', (string) $header, $m, PREG_SET_ORDER);

            return array_map(fn (array $match): array => ['email' => $match[2], 'name' => trim($match[1] ?? '')], $m);
        };

        return $mails->map(fn (Mail $mail): array => ['from' => $parse($mail->from), 'to' => $parse($mail->to)])->values()->all();
    }

    /**
     * Whether every message of the click was sent from your own address: the
     * dialog then says "Recipients" rather than "Sender".
     *
     * @param  array<int, array{from?: mixed, to?: mixed}>  $parties
     */
    protected function allOutgoing(array $parties, MailAccount $account): bool
    {
        $own = array_filter(array_map('mb_strtolower', [(string) $account->email, (string) Auth::user()?->email,
            ...(Auth::user()?->contact?->collection('emails')->pluck('value')->all() ?? [])]));

        return $parties !== [] && collect($parties)->every(fn ($message): bool => collect(is_array($message['from'] ?? null) ? $message['from'] : [])
            ->contains(fn ($entry): bool => in_array(mb_strtolower(trim((string) (is_array($entry) ? ($entry['email'] ?? '') : $entry))), $own, true)));
    }

    /** Never a success that hides a failure. */
    protected function notArchived(): void
    {
        Notification::make()
            ->title(__('The message was not archived'))
            ->body(__('The mail server did not return it. Reload the Mailbox page and click Archive again.'))
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * Say what happened and offer to link the messages to a record — every
     * time, as linking is part of archiving. When the mail server refused the
     * move, the dialog says so: the messages are archived all the same. A sent
     * copy ($sent) was saved into the archive folder by Roundcube, not moved.
     *
     * @param  Collection<int, Mail>  $mails
     */
    protected function afterArchiving(Collection $mails, MailAccount $account, ?string $moveError = null, bool $sent = false): void
    {
        $this->mountAction('archivedDialog', [
            'mails' => $mails->map->getKey()->values()->all(),
            'sent' => $sent,
            // Archived before, by anyone: MailArchiver linked the existing copy to you.
            'already' => $mails->reject->wasRecentlyCreated->count(),
            'folder' => $account->archive_folder,
            'moveError' => $moveError,
        ]);
    }

    /** "Message archived", or "Message already archived" when none of them is new. */
    protected static function archivedHeading(array $arguments): string
    {
        $count = count($arguments['mails'] ?? []);

        if ($arguments['sent'] ?? false) {
            return trans_choice('{1} Message sent and archived|[2,*] :count messages sent and archived', $count, ['count' => $count]);
        }

        if ($count > 0 && ($arguments['already'] ?? 0) >= $count) {
            return trans_choice('{1} Message already archived|[2,*] :count messages already archived', $count, ['count' => $count]);
        }

        return trans_choice('{1} Message archived|[2,*] :count messages archived', $count, ['count' => $count]);
    }

    protected static function archivedDescription(array $arguments): string
    {
        $folder = $arguments['folder'] ?? 'CRM Archive';
        $count = count($arguments['mails'] ?? []);
        $already = (int) ($arguments['already'] ?? 0);

        if ($arguments['sent'] ?? false) {
            return __('Sent message archived in Epesi and saved to :folder folder', ['folder' => $folder]);
        }

        $text = match (true) {
            filled($arguments['moveError'] ?? null) => __('Message archived in Epesi, but not moved to :folder folder: the mail server refused the move (:error). The message is still in your inbox.', ['folder' => $folder, 'error' => $arguments['moveError']]),
            $count > 0 && $already >= $count => __('It was already in Epesi\'s archive: it is now linked to you too, and moved to :folder folder.', ['folder' => $folder]),
            default => __('Message archived in Epesi and moved to :folder folder', ['folder' => $folder]),
        };

        if ($already > 0 && $already < $count) {
            $text .= ' '.trans_choice('{1} One of them was already archived: it is now linked to you too.|[2,*] :count of them were already archived: they are now linked to you too.', $already, ['count' => $already]);
        }

        return $text;
    }

    /**
     * Explains what the Archive button (or archiving on send) did, and links
     * the messages to more records if wanted.
     */
    public function archivedDialogAction(): Action
    {
        return Action::make('archivedDialog')
            ->modalHeading(fn (array $arguments): string => static::archivedHeading($arguments))
            ->modalDescription(fn (array $arguments): string => static::archivedDescription($arguments))
            ->modalIcon(fn (array $arguments): Heroicon => filled($arguments['moveError'] ?? null) ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedArchiveBox)
            ->modalIconColor(fn (array $arguments): string => filled($arguments['moveError'] ?? null) ? 'warning' : 'primary')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel(__('OK'))
            ->modalCancelAction(false)
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema([static::linksRepeater()])
            ->action(function (array $data, array $arguments): void {
                $records = static::pickedRecords($data['links'] ?? []);

                if ($records->isEmpty()) {
                    return;
                }

                $this->linkMails(Mail::query()->whereKey($arguments['mails'] ?? [])->get(), $records);

                Notification::make()
                    ->title(__('Linked to :record', ['record' => $records->map(fn (Model $record): string => Records::label($record))->implode(', ')]))
                    ->success()
                    ->send();
            });
    }

    /** "Also link to": one line per record, Recordset then Record, as many as wanted. */
    protected static function linksRepeater(): Repeater
    {
        return Repeater::make('links')
            ->label(__('Also link to'))
            ->helperText(__('Optional: a task, meeting, phone call or any other record.'))
            ->table([
                TableColumn::make(__('Recordset')),
                TableColumn::make(__('Record')),
            ])
            ->schema([
                LinkRecordAction::typeField(null),
                LinkRecordAction::recordField()->requiredWith('type'),
            ])
            ->defaultItems(1)
            ->reorderable(false)
            ->addActionLabel(__('Add another record'));
    }

    /**
     * The records picked in "Also link to", each once; empty lines ignored.
     *
     * @return Collection<int, Model>
     */
    protected static function pickedRecords(array $links): Collection
    {
        return collect($links)
            ->map(fn (mixed $link): ?Model => is_array($link) && filled($link['type'] ?? null) && filled($link['record'] ?? null)
                ? LinkRecordAction::find($link['type'], $link['record'])
                : null)
            ->filter()
            ->unique(fn (Model $record): string => $record->getMorphClass().':'.$record->getKey())
            ->values();
    }

    /**
     * @param  Collection<int, Mail>  $mails
     * @param  Collection<int, Model>  $records
     */
    protected function linkMails(Collection $mails, Collection $records): void
    {
        foreach ($mails as $mail) {
            if (Auth::user()?->can('update', $mail)) {
                $records->each(fn (Model $record) => $mail->linkTo($record));
            }
        }
    }

    /**
     * What the dialog after archiving would say, as a notification: after the
     * unknown-sender dialog, one dialog per click is enough.
     *
     * @param  Collection<int, Mail>  $mails
     */
    protected function archivedNotification(Collection $mails, MailAccount $account, ?string $moveError = null, bool $sent = false): void
    {
        $arguments = [
            'mails' => $mails->modelKeys(),
            'sent' => $sent,
            'already' => $mails->reject->wasRecentlyCreated->count(),
            'folder' => $account->archive_folder,
            'moveError' => $moveError,
        ];

        Notification::make()
            ->title(static::archivedHeading($arguments))
            ->body(static::archivedDescription($arguments))
            ->when(filled($moveError), fn (Notification $n) => $n->warning()->persistent(), fn (Notification $n) => $n->success())
            ->send();
    }

    /**
     * "Sender not in Contacts": before archiving (or, for a sent copy, just
     * after), one row per unknown address — add it to an existing contact,
     * create the contact (and its company), add it to a company, or not at
     * all — then "Also link to", then Archive. Cancel archives nothing.
     */
    public function unknownSendersAction(): Action
    {
        return Action::make('unknownSenders')
            ->modalHeading(fn (array $arguments): string => ($arguments['outgoing'] ?? false)
                ? trans_choice('{1} Recipient not in Contacts|[2,*] Recipients not in Contacts', count($arguments['unknown'] ?? []))
                : trans_choice('{1} Sender not in Contacts|[2,*] Senders not in Contacts', count($arguments['unknown'] ?? [])))
            ->modalDescription(fn (array $arguments): string => ($arguments['sent'] ?? false)
                ? __('The message is archived. Add these addresses to the CRM, and the message is linked to them.')
                : __('Add these addresses to the CRM before archiving, or archive without them.'))
            ->modalIcon(Heroicon::OutlinedUserPlus)
            ->modalIconColor('warning')
            ->modalWidth('4xl')
            ->modalSubmitActionLabel(fn (array $arguments): string => ($arguments['sent'] ?? false) ? __('OK') : __('Archive'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->fillForm(fn (array $arguments): array => ['senders' => collect($arguments['unknown'] ?? [])
                ->map(fn (mixed $entry): array => UnknownSenderRow::defaults((array) $entry))->values()->all()])
            ->schema([
                Repeater::make('senders')
                    ->hiddenLabel()
                    ->schema(UnknownSenderRow::schema())
                    ->itemLabel(fn (array $state): string => UnknownSenderRow::label($state))
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),
                static::linksRepeater(),
            ])
            ->action(function (array $data, array $arguments): void {
                $allowed = collect($arguments['unknown'] ?? [])->pluck('email')->map(fn ($e): string => mb_strtolower((string) $e))->all();

                DB::transaction(function () use ($data, $allowed): void {
                    foreach ((array) ($data['senders'] ?? []) as $row) {
                        // Only the addresses this dialog was opened for.
                        if (is_array($row) && in_array(mb_strtolower((string) ($row['email'] ?? '')), $allowed, true)) {
                            UnknownSenderRow::apply($row);
                        }
                    }
                });

                $links = static::pickedRecords($data['links'] ?? []);

                if ($arguments['sent'] ?? false) {
                    $mails = Mail::query()->whereKey($arguments['mails'] ?? [])->get();
                    $this->linkMails($mails, $links);

                    if ($account = $this->account()) {
                        $this->archivedNotification($mails, $account, sent: true);
                    }

                    return;
                }

                if ($this->archiveNow((string) ($arguments['folder'] ?? ''), (array) ($arguments['uids'] ?? []), $links)) {
                    $this->dispatch('epesi-roundcube-refresh');
                }
            });
    }

    protected function getHeaderActions(): array
    {
        $accounts = $this->accounts();

        if ($accounts->count() < 2) {
            return [];
        }

        return [
            ActionGroup::make($accounts->map(fn (MailAccount $account): Action => Action::make('account'.$account->getKey())
                ->label($account->name)
                ->icon($account->getKey() === $this->accountId ? Heroicon::OutlinedCheck : Heroicon::OutlinedInbox)
                ->action(fn () => $this->switchAccount($account->getKey())))->all())
                ->label($accounts->firstWhere('id', $this->accountId)?->name ?? __('Account'))
                ->icon(Heroicon::OutlinedInboxStack)
                ->color('primary')
                ->button(),
        ];
    }

    /**
     * Your own accounts with an incoming server — never anyone else's, however
     * the page is called.
     *
     * @return Builder<MailAccount>
     */
    protected function accountQuery(): Builder
    {
        return MailAccount::query()->where('user_id', Auth::id())->whereNotNull('imap_host');
    }

    /**
     * Keeps $frameUrl (not just the transient browser event) pointing at the
     * account actually loaded — otherwise a re-render that rebuilds the
     * wire:ignore frame div (x-init reruns) would reload the stale account
     * while the header selector already shows the new one.
     */
    protected function load(MailAccount $account): void
    {
        $this->frameUrl = $this->ticketUrl($account);

        if ($this->frameUrl !== null) {
            $this->dispatch('epesi-roundcube-load', url: $this->frameUrl, folder: $this->rememberedFolder());
        }
    }

    /**
     * Null, with $oauthError set, when an OAuth account has no valid access
     * token to log in with (XOAUTH2): not connected, or it must be connected
     * again. Its message never carries a token.
     */
    protected function ticketUrl(MailAccount $account): ?string
    {
        /** @var User $user */
        $user = Auth::user();
        $this->oauthError = null;

        try {
            return app(TicketIssuer::class)->url($account, $user);
        } catch (OAuthException $e) {
            $this->oauthError = $e->getMessage();

            return null;
        }
    }
}
