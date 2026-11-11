<?php

namespace Epesi\Modules\MigrationWizard;

use Epesi\Modules\MigrationWizard\Http\MigrationReportAdministrationController;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

/**
 * "administration": the Migration Report page (a link to the report and the log of a finished migration) and the
 * route that serves their files. The page appears only once a migration has written them.
 */
class MigrationWizardPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-migration-wizard';
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() !== 'administration') {
            return;
        }

        $panel
            ->discoverPages(
                in: __DIR__.'/Filament/Administration/Pages',
                for: 'Epesi\Modules\MigrationWizard\Filament\Administration\Pages',
            )
            ->authenticatedRoutes(function (): void {
                Route::get('/migration-report/{format}', MigrationReportAdministrationController::class)
                    ->whereIn('format', ['html', 'txt'])
                    ->name('migration-report.file');
            });
    }

    public function boot(Panel $panel): void {}
}
