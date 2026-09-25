<?php

namespace Epesi\Modules\Roundcube\Filament\Pages;

use App\Filament\Actions\InstallRoundcubeAction;
use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\User;
use BackedEnum;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\MailAccountResource;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\MailFetcher;
use Epesi\Modules\Roundcube\Roundcube;
use Epesi\Modules\Roundcube\Services\TicketIssuer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * Your mailbox in the Roundcube webmail — Epesi's CRM_Roundcube screen. The
 * frame logs in with a one-time ticket (TicketIssuer) and talks back through
 * postMessage: `archived` when its Archive button moved mail into the
 * archive folder, `login-required` when its session ran out.
 */
class Mailbox extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

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
        return Auth::user()?->hasAnyRole(['super_admin', 'manager', 'employee']) ?? false;
    }

    public function mount(): void
    {
        $account = $this->accounts()->first();

        $this->accountId = $account?->getKey();
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
            $this->load($account);
        }
    }

    /** The frame's session is gone: log it in again. */
    public function relogin(): void
    {
        if ($account = $this->account()) {
            $this->load($account);
        }
    }

    /** The frame moved mail into the archive folder: archive it now, not at the next scheduled fetch. */
    public function archived(): void
    {
        $account = $this->account();

        if (! $account) {
            return;
        }

        try {
            $count = app(MailFetcher::class)->fetch($account);
        } catch (Throwable $e) {
            Notification::make()->title(__('Archiving failed'))->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title(trans_choice('{0} No messages archived|{1} :count message archived|[2,*] :count messages archived', $count, ['count' => $count]))
            ->success()
            ->send();
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
                ->color('gray')
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

    protected function load(MailAccount $account): void
    {
        $this->dispatch('epesi-roundcube-load', url: $this->ticketUrl($account));
    }

    protected function ticketUrl(MailAccount $account): string
    {
        /** @var User $user */
        $user = Auth::user();

        return app(TicketIssuer::class)->url($account, $user);
    }
}
