<?php

namespace Epesi\Modules\Reminders;

use Epesi\Modules\Reminders\Filament\Widgets\MyRemindersWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

class RemindersPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-reminders';
    }

    public function register(Panel $panel): void
    {
        $panel
            // The Messenger applet ("Messenger alarms") on the dashboard.
            ->widgets([MyRemindersWidget::class])
            // Due reminders land in the bell, where Epesi popped up a confirm
            // box. Enabled here too so reminders work without Watchdog.
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s');
    }

    public function boot(Panel $panel): void {}
}
