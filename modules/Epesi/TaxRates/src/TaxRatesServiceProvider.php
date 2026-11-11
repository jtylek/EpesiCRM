<?php

namespace Epesi\Modules\TaxRates;

use Epesi\Modules\TaxRates\Models\TaxRate;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Tax rates and their codes.
 *
 * A core module (`"core": true` in module.json): Accounting's document lines
 * and Inventory's items point at these rates, and an installation without
 * Accounting still needs them.
 */
class TaxRatesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Relation::enforceMorphMap(['tax_rate' => TaxRate::class]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Before the core tabs: invoice, expense and item lines link to these
        // rates by their legacy ids. The tab name is the one it had in Accounting.
    }
}
