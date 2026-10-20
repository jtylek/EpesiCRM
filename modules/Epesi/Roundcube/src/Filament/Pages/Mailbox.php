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
use Epesi\Modules\Mail\Support\Records;
use Epesi\Modules\Roundcube\Roundcube;
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
        $this->frameUrl = $account && $this->isInstalled() ? $this->ticketUrl($account) : null;
    }

    public function isInstalled(): bool
    {
        return Roundcube::isInstalled();
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
        }
    }

    /**
     * The frame's Archive button, after its checks: archive these messages
     * from the folder the user is in, then move them to the archive folder.
     * Returns whether any left the folder, so the frame refreshes its list.
     *
     * @param  array<int, int>  $uids
     */
    public function archive(string $folder, array $uids): bool
    {
        $account = $this->account();

        if (! $account || $folder === '' || mb_strlen($folder) > 255) {
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

        $this->afterArchiving($result['mails'], $account, $result['moveError']);

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

        // The same dialog as the Archive button's, to link the sent message to records.
        $mails->isEmpty() ? $this->notArchived() : $this->afterArchiving($mails, $account, sent: true);
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
            ->schema([
                // One line per record: Recordset, then Record; as many as wanted.
                Repeater::make('links')
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
                    ->addActionLabel(__('Add another record')),
            ])
            ->action(function (array $data, array $arguments): void {
                $records = collect($data['links'] ?? [])
                    ->map(fn (array $link): ?Model => filled($link['type'] ?? null) && filled($link['record'] ?? null)
                        ? LinkRecordAction::find($link['type'], $link['record'])
                        : null)
                    ->filter()
                    ->unique(fn (Model $record): string => $record->getMorphClass().':'.$record->getKey());

                if ($records->isEmpty()) {
                    return;
                }

                foreach (Mail::query()->whereKey($arguments['mails'] ?? [])->get() as $mail) {
                    if (Auth::user()?->can('update', $mail)) {
                        $records->each(fn (Model $record) => $mail->linkTo($record));
                    }
                }

                Notification::make()
                    ->title(__('Linked to :record', ['record' => $records->map(fn (Model $record): string => Records::label($record))->implode(', ')]))
                    ->success()
                    ->send();
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
        $this->dispatch('epesi-roundcube-load', url: $this->frameUrl, folder: $this->rememberedFolder());
    }

    protected function ticketUrl(MailAccount $account): string
    {
        /** @var User $user */
        $user = Auth::user();

        return app(TicketIssuer::class)->url($account, $user);
    }
}
