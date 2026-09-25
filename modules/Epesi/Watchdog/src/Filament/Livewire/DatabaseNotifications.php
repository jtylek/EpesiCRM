<?php

namespace Epesi\Modules\Watchdog\Filament\Livewire;

use Epesi\Modules\Watchdog\Watchdog;
use Filament\Livewire\DatabaseNotifications as BaseDatabaseNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

/**
 * The panel's bell. Filament only sets read_at on its notifications, so a
 * change read or dismissed here would stay new on the Watched page: each of
 * these marks the changes seen first. Watchdog::markSeen() goes the other
 * way. Only unread notifications matter — a read one was seen when it was
 * read.
 */
class DatabaseNotifications extends BaseDatabaseNotifications
{
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

    public function clearNotifications(): void
    {
        $this->markSeen($this->getUnreadNotificationsQuery());

        parent::clearNotifications();
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
