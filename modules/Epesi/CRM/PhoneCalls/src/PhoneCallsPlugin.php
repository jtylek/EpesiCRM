<?php

namespace Epesi\Modules\CRM\PhoneCalls;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * What puts this module's screens into the main panel. The instance comes
 * from ModuleRegistry::pluginsFor() (driven by the `modules` table) rather
 * than a hand-edited ->plugins([]) array in a panel provider.
 */
class PhoneCallsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi/crm-phonecalls';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: __DIR__.'/Filament/Resources',
            for: 'Epesi\Modules\CRM\PhoneCalls\\Filament\\Resources',
        );
    }

    public function boot(Panel $panel): void {}
}
