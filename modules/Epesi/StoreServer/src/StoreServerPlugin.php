<?php

namespace Epesi\Modules\StoreServer;

use Filament\Contracts\Plugin;
use Filament\Panel;

class StoreServerPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-store-server';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: __DIR__.'/Filament/Resources',
            for: 'Epesi\\Modules\\StoreServer\\Filament\\Resources',
        );
    }

    public function boot(Panel $panel): void {}
}
