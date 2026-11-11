<?php

namespace Epesi\Modules\MigrationWizard;

use App\Services\LegacyImport\LegacyImportSet;
use Epesi\Modules\MigrationWizard\Console\MigrateLegacyCommand;
use Epesi\Modules\MigrationWizard\Detection\ApprovedLegacySchemas;
use Epesi\Modules\MigrationWizard\Detection\ImporterDeclarations;
use Epesi\Modules\MigrationWizard\Detection\LegacyConfigReader;
use Epesi\Modules\MigrationWizard\Detection\LegacyInstallation;
use Epesi\Modules\MigrationWizard\Detection\LegacyInventory;
use Epesi\Modules\MigrationWizard\Detection\LegacyModuleCatalog;
use Epesi\Modules\MigrationWizard\Detection\ModuleCoverage;
use Epesi\Modules\MigrationWizard\Detection\SchemaFingerprint;
use Epesi\Modules\MigrationWizard\Import\Importers\UsersImporter;
use Epesi\Modules\MigrationWizard\Import\InPlaceLegacyImport;
use Epesi\Modules\MigrationWizard\Import\LegacyImporterRegistry;
use Epesi\Modules\MigrationWizard\Import\LegacyImportRunner;
use Illuminate\Support\ServiceProvider;

/**
 * Foundation only: no database access, routes or destructive operations at boot.
 */
final class MigrationWizardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/migration-wizard.php', 'migration-wizard');
        $this->app->singleton(LegacyImporterRegistry::class);
        $this->app->singleton(LegacyImportRunner::class);
        // Resolved lazily (after every module registered its importers): the catalog, the
        // importers' declarations and the approved schemas feed the one coverage classification.
        $this->app->bind(LegacyInstallation::class, function ($app): LegacyInstallation {
            $known = (new LegacyModuleCatalog)->knownModules();
            $importers = (new ImporterDeclarations)->build($app->make(LegacyImporterRegistry::class)->all(), $known);

            return new LegacyInstallation(
                new LegacyConfigReader,
                new SchemaFingerprint,
                new ModuleCoverage,
                new LegacyInventory,
                $known,
                $importers,
                (new ApprovedLegacySchemas)->all(),
                inPlaceImporters: InPlaceLegacyImport::moduleImporters($app->make(LegacyImportSet::class)->importers()),
            );
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'migration-wizard');
        $this->app->make(LegacyImporterRegistry::class)->register(new UsersImporter);

        if ($this->app->runningInConsole()) {
            $this->commands([MigrateLegacyCommand::class]);
        }
    }
}
