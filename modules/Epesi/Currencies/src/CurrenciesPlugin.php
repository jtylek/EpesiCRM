<?php

namespace Epesi\Modules\Currencies;

use Epesi\Modules\Currencies\Filament\Widgets\CurrencyConverterWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

class CurrenciesPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-currencies';
    }

    public function register(Panel $panel): void
    {
        // The main panel only gets the Currency Converter applet; the rest is administration.
        if ($panel->getId() === 'main') {
            $panel->widgets([CurrencyConverterWidget::class]);

            return;
        }

        $panel->discoverResources(
            in: __DIR__.'/Filament/Resources',
            for: 'Epesi\\Modules\\Currencies\\Filament\\Resources',
        );
    }

    public function boot(Panel $panel): void {}
}
