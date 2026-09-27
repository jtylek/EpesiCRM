<?php

namespace Epesi\Modules\Watchdog\Filament\Livewire;

use Epesi\Modules\Watchdog\Watchdog;
use Filament\Actions\Action;
use Filament\Livewire\DatabaseNotifications as BaseDatabaseNotifications;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

/**
 * The panel's bell. It lists only unread notifications, as Epesi's tray
 * listed only unseen changes: one read anywhere, or whose record was opened,
 * leaves the list. With read ones gone, Clear would do what Mark all as read
 * does, so only the latter is offered.
 *
 * Filament only sets read_at on its notifications, so a change read or
 * dismissed here would stay new on the Watched page: each of these marks the
 * changes seen first. Watchdog::markSeen() goes the other way. Only unread
 * notifications matter — a read one was seen when it was read.
 */
class DatabaseNotifications extends BaseDatabaseNotifications
{
    /** Days a read notification is kept, out of the bell's sight, before pruneRead() deletes it. */
    public const KEEP_READ_DAYS = 30;

    /**
     * Only the list is limited to unread, not getNotificationsQuery(): the
     * methods below mark a notification read before Filament deletes it
     * through that query.
     */
    public function getNotifications(): DatabaseNotificationCollection|Paginator
    {
        $unread = $this->getUnreadNotificationsQuery();

        return $this->isPaginated()
            ? $unread->simplePaginate(50, pageName: 'database-notifications-page')
            : $unread->get();
    }

    public function clearNotificationsAction(): Action
    {
        return parent::clearNotificationsAction()->hidden();
    }

    #[On('markedNotificationAsRead')]
    public function markNotificationAsRead(string $id): void
    {
        if (Str::isUuid($id)) {
            $this->markSeen($this->getUnreadNotificationsQuery()->whereKey($id));
        }

        parent::markNotificationAsRead($id);
    }

    public function markAllNotificationsAsRead(): void
    {
        $this->markSeen($this->getUnreadNotificationsQuery());

        parent::markAllNotificationsAsRead();
    }

    #[On('notificationClosed')]
    public function removeNotification(string $id): void
    {
        if (Str::isUuid($id)) {
            $this->markSeen($this->getUnreadNotificationsQuery()->whereKey($id));
        }

        parent::removeNotification($id);
    }

    /**
     * Read notifications older than KEEP_READ_DAYS, which nothing shows any
     * more. Run daily by the scheduler (WatchdogServiceProvider).
     */
    public static function pruneRead(): int
    {
        return DatabaseNotification::query()
            ->where('data->format', 'filament')
            ->where('read_at', '<', now()->subDays(static::KEEP_READ_DAYS))
            ->delete();
    }

    protected function markSeen(Builder|Relation $notifications): void
    {
        $notifications = $notifications->get();

        if ($notifications->isEmpty()) {
            return;
        }

        Watchdog::markNotifiedSeen($this->getUser(), $notifications);

        // The Watched page's count in the menu, and the page itself if open.
        $this->dispatch('refresh-sidebar');
        $this->dispatch('watchdog-seen');
    }
}
