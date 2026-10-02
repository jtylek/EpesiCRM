<?php

namespace Epesi\Modules\Mail\Filament\Widgets;

use App\Filament\Dashboard\Applet;
use App\Filament\Dashboard\IsApplet;
use App\Models\User;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\MailAccountResource;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\UnreadCounter;
use Epesi\Modules\Roundcube\Filament\Pages\Mailbox;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The Mail applet: unread messages in each of your accounts' INBOX — Epesi's
 * CRM_Mail applet and its unread-count tray notification.
 */
class UnreadMailWidget extends Widget implements Applet
{
    use IsApplet;

    protected string $view = 'epesi-mail::unread-widget';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->hasAnyRole(['super_admin', 'manager', 'employee'])
            && MailAccount::query()->where('user_id', $user->id)->whereNotNull('imap_host')->exists();
    }

    public static function getAppletCaption(): string
    {
        return __('Mail');
    }

    public static function getAppletDescription(): ?string
    {
        return __('Unread mail in your accounts');
    }

    public function refreshCounts(): void
    {
        foreach ($this->accounts() as $account) {
            app(UnreadCounter::class)->forAccount($account, refresh: true);
        }
    }

    /**
     * @return Collection<int, MailAccount>
     */
    public function accounts(): Collection
    {
        return MailAccount::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('imap_host')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{count: int|null, error: string|null}
     */
    public function unread(MailAccount $account): array
    {
        return app(UnreadCounter::class)->forAccount($account);
    }

    public function accountsUrl(): string
    {
        return MailAccountResource::getUrl('index', panel: 'user-settings');
    }

    /** Null when the Roundcube module isn't installed — there's no mailbox page to link to. */
    public function defaultMailboxUrl(): ?string
    {
        $account = $this->accounts()->first();

        return $account ? $this->mailboxUrl($account) : null;
    }

    /** Null when the Roundcube module isn't installed — there's no mailbox page to link to. */
    public function mailboxUrl(MailAccount $account): ?string
    {
        return class_exists(Mailbox::class) ? Mailbox::getUrl(['account' => $account->getKey()]) : null;
    }
}
