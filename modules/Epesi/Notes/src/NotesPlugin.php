<?php

namespace Epesi\Modules\Notes;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * What puts this module's screens into a panel. Filament's standard plugin
 * contract — the only Epesi-specific part is that the instance comes from
 * ModuleRegistry::pluginsFor() (driven by the `modules` table) rather than a
 * hand-edited ->plugins([]) array in a panel provider.
 */
class NotesPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-notes';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: __DIR__.'/Filament/Resources',
            for: 'Epesi\\Modules\\Notes\\Filament\\Resources',
        );
    }

    public function boot(Panel $panel): void {}
}
