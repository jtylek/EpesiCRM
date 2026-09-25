<?php

namespace Epesi\Modules\Store;

use Epesi\Modules\Store\Filament\Pages\Store;
use Filament\Contracts\Plugin;
use Filament\Panel;

class StorePlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-store';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            Store::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
