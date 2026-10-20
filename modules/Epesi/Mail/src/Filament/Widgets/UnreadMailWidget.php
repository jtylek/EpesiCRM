<?php

namespace Epesi\Modules\Mail\Filament\Widgets;

use App\Filament\Dashboard\Applet;
use App\Filament\Dashboard\IsApplet;
use App\Models\User;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\MailAccountResource;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\UnreadCounter;
use Epesi\Modules\Mail\Services\UnreadMailCheckDispatcher;
use Epesi\Modules\Roundcube\Filament\Pages\Mailbox;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Isolate;

/**
 * The Mail applet: unread messages in each of your accounts' INBOX — Epesi's
 * CRM_Mail applet and its unread-count tray notification. Isolated, so a
 * request that asks a mail server isn't bundled with other widgets' updates.
 */
#[Isolate]
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
            && $user->hasAnyRole(User::CRM_ROLES)
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
            app(UnreadMailCheckDispatcher::class)->dispatch($account);
        }
    }

    /**
     * Asks the server only for the accounts with no cached count — called
     * after the widget is already on screen, so rendering never waits on a
     * mail server even when cron hasn't filled the cache.
     */
    public function checkMissing(): void
    {
        $counter = app(UnreadCounter::class);

        foreach ($this->accounts() as $account) {
            if ($counter->cached($account) === null) {
                app(UnreadMailCheckDispatcher::class)->dispatch($account);
            }
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
     * Null while there is no cached count yet; checkMissing() fills it in.
     *
     * @return array{count: int|null, error: string|null}|null
     */
    public function unread(MailAccount $account): ?array
    {
        return app(UnreadCounter::class)->cached($account);
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
