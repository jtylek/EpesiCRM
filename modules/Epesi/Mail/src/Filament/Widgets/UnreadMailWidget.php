<?php

namespace Epesi\Modules\Mail\Filament\Widgets;

use Epesi\Modules\Mail\Filament\Resources\MailAccounts\MailAccountResource;
use Epesi\Modules\Mail\Filament\Resources\Mails\MailResource;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\UnreadCounter;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The Mail applet: unread messages in each of your accounts' INBOX, and the
 * latest mail archived from your mailboxes — Epesi's CRM_Mail applet and its
 * unread-count tray notification.
 */
class UnreadMailWidget extends Widget
{
    protected string $view = 'epesi-mail::unread-widget';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 4;

    public const RECENT = 5;

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user !== null
            && $user->hasAnyRole(['super_admin', 'manager', 'employee'])
            && MailAccount::query()->where('user_id', $user->id)->whereNotNull('imap_host')->exists();
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

    /**
     * @return Collection<int, Mail>
     */
    public function recent(): Collection
    {
        return Mail::query()
            ->where('user_id', Auth::id())
            ->latest('date')
            ->limit(self::RECENT)
            ->get(['id', 'subject', 'from', 'to', 'date', 'direction']);
    }

    public function mailUrl(Mail $mail): string
    {
        return MailResource::getUrl('view', ['record' => $mail]);
    }

    public function accountsUrl(): string
    {
        return MailAccountResource::getUrl('index', panel: 'user-settings');
    }
}
