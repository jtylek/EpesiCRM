<?php

namespace Epesi\Modules\RegionalSettings;

use Epesi\Modules\RegionalSettings\Filament\Pages\RegionalSettings;
use Filament\Contracts\Plugin;
use Filament\Panel;

class RegionalSettingsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-regional-settings';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            RegionalSettings::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
