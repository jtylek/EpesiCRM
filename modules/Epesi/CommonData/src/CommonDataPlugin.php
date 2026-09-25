<?php

namespace Epesi\Modules\CommonData;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Puts Administration → Common Data into the administration panel — the
 * analogue of Epesi's Administration → Common Data screen
 * (`Utils_CommonData::browse()`).
 */
class CommonDataPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-commondata';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: __DIR__.'/Filament/Resources',
            for: 'Epesi\\Modules\\CommonData\\Filament\\Resources',
        );
    }

    public function boot(Panel $panel): void {}
}
