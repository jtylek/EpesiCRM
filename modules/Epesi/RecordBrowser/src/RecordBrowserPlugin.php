<?php

namespace Epesi\Modules\RecordBrowser;

use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetAddons;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetClipboard;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetFields;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetRecords;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\Recordsets;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetSettings;
use Epesi\Modules\RecordBrowser\Filament\Administration\RecordInAdministration;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Puts Administration → Fields (the custom-field editor) into the
 * administration panel — the direct analogue of Epesi's
 * Administration → Records Browser screen. The engine's own base classes need
 * no panel registration; they are extended, not discovered.
 */
class RecordBrowserPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-recordbrowser';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: __DIR__.'/Filament/Resources',
            for: 'Epesi\\Modules\\RecordBrowser\\Filament\\Resources',
        )->pages([
            Recordsets::class,
            RecordsetFields::class,
            RecordsetRecords::class,
            RecordsetAddons::class,
            RecordsetSettings::class,
            RecordsetClipboard::class,
        ])->authenticatedRoutes(fn () => RecordInAdministration::routes());
    }

    public function boot(Panel $panel): void {}
}
