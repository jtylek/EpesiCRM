<?php

namespace Epesi\Modules\Watchdog;

use Epesi\Modules\Watchdog\Filament\Livewire\DatabaseNotifications;
use Epesi\Modules\Watchdog\Filament\Pages\Watched;
use Filament\Contracts\Plugin;
use Filament\Panel;

class WatchdogPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-watchdog';
    }

    public function register(Panel $panel): void
    {
        $panel
            // No dashboard applet: it listed what the bell and the Watched
            // page already show.
            ->pages([Watched::class])
            // The bell in the top bar: Epesi's tray notifications.
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s');
    }

    /**
     * The bell that marks changes seen. Set here, not in register(): another
     * module turning the bell on with databaseNotifications() after this one
     * would put Filament's own back. WatchdogServiceProvider registers it
     * with Livewire, which Filament does only for the one set by then.
     */
    public function boot(Panel $panel): void
    {
        $panel->databaseNotificationsLivewireComponent(DatabaseNotifications::class);
    }
}
