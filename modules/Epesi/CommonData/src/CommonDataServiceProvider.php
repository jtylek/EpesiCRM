<?php

namespace Epesi\Modules\CommonData;

use App\Services\LegacyImport\ImporterRegistry;
use Epesi\Modules\CommonData\LegacyImport\CommonDataImporter;
use Illuminate\Support\ServiceProvider;

/**
 * Shared reference data.
 *
 * A core module (`"core": true` in module.json): recordset fields select from
 * these lists, so ModuleInstaller refuses to disable or uninstall it once
 * anything points at an array.
 */
class CommonDataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No Eloquent here — a service provider's register() runs before the
        // database connection resolver exists.
        $this->app->singleton(CommonDataRepository::class);
    }

    public function boot(): void
    {
        // Keeps `php artisan migrate` aware of this module's migrations after
        // install; the installer runs them once itself with --path.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // `first: true` — reference data is what the record tabs are read
        // against, and it depends on nothing itself.
        $this->app->make(ImporterRegistry::class)->register('commondata', CommonDataImporter::class, first: true);
    }
}
