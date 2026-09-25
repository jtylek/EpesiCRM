<?php

namespace Epesi\Modules\Roundcube;

use Epesi\Modules\Roundcube\Filament\Pages\Mailbox;
use Filament\Contracts\Plugin;
use Filament\Panel;

class RoundcubePlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-roundcube';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([Mailbox::class]);
    }

    public function boot(Panel $panel): void {}
}
