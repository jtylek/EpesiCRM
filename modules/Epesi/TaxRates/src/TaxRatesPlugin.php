<?php

namespace Epesi\Modules\TaxRates;

use Epesi\Modules\TaxRates\Filament\Resources\TaxRates\TaxRateResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class TaxRatesPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-taxrates';
    }

    /** Both panels; the menu entry is the administration panel's (TaxRateResource). */
    public function register(Panel $panel): void
    {
        $panel->resources([TaxRateResource::class]);
    }

    public function boot(Panel $panel): void {}
}
