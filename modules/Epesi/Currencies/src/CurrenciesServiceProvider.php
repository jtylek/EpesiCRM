<?php

namespace Epesi\Modules\Currencies;

use App\Services\LegacyImport\ImporterRegistry;
use Epesi\Modules\Currencies\LegacyImport\CurrenciesImporter;
use Epesi\Modules\Currencies\Models\CurrencySetting;
use Epesi\Modules\Currencies\Services\CurrencyRepository;
use Epesi\Modules\Currencies\Services\RateFetcher;
use Epesi\Modules\Currencies\Services\RateProviders;
use Epesi\Modules\Currencies\Services\RateResolver;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Currencies, the home currency and daily exchange rates.
 *
 * A core module (`"core": true` in module.json): invoices, expenses and the
 * Currency field type book amounts against these codes and rates.
 */
class CurrenciesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrencyRepository::class);
        $this->app->singleton(RateProviders::class);
        $this->app->singleton(RateResolver::class);
        $this->app->singleton(RateFetcher::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'epesi-currencies');

        // Before the core tabs: amounts they import name these currencies.
        $this->app->make(ImporterRegistry::class)->register('currencies', CurrenciesImporter::class, first: true);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // The ECB publishes around 16:00 CET, the NBP around 12:15, so by
            // 17:00 UTC both have today's rates.
            $schedule->call(fn () => app(RateFetcher::class)->fetch())
                ->name('Currencies: fetch daily exchange rates')
                ->dailyAt('17:00')
                ->when(fn (): bool => rescue(fn () => CurrencySetting::current()->auto_fetch, false, report: false));
        });
    }
}
