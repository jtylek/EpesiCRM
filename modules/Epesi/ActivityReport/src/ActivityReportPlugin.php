<?php

namespace Epesi\Modules\ActivityReport;

use Epesi\Modules\ActivityReport\Filament\Pages\ActivityDashboard;
use Epesi\Modules\ActivityReport\Filament\Pages\UserActivityReport;
use Filament\Contracts\Plugin;
use Filament\Panel;

class ActivityReportPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-activity-report';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([ActivityDashboard::class, UserActivityReport::class]);
    }

    public function boot(Panel $panel): void {}
}
