<?php

namespace Epesi\Modules\MigrationWizard\Install;

use App\Models\User;
use App\Services\Setup\Installer;
use App\Services\Setup\InstallOptions;
use App\Services\Setup\SetupException;
use Database\Seeders\RoleSeeder;
use Epesi\Modules\MigrationWizard\Database\LegacyTableNamespace;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the Epesi 3 schema and modules in the namespace the table rename has
 * just freed. It reuses the services behind `epesi:install` (the core
 * migrations, RoleSeeder, ModulePlan, ModuleInstaller and ModuleLoader through
 * the parent Installer) rather than a second implementation.
 *
 * What it deliberately does not do, so setup-only access lasts until
 * verification completes: create an administrator, write the installed marker,
 * switch .env to production or configure mail. The password hand-off and the
 * final marker belong to later wizard steps.
 */
class EpesiInstaller extends Installer
{
    /**
     * @param  array<string, string>  $renameMap  the exact old => new map LegacyTableNamespace applied
     * @param  list<string>  $optionalModulePaths  extra module paths required by supported importers (e.g. Epesi/Roundcube)
     * @return array{modules: list<string>}
     */
    public function prepare(array $renameMap, array $optionalModulePaths = []): array
    {
        $this->assertNamespaceFree($renameMap);

        @set_time_limit(0);

        $this->migrate();
        $this->seedRoles();
        $modules = $this->installModules($optionalModulePaths);

        return ['modules' => array_values($modules)];
    }

    /** The administrator and the installed marker are not this class's to create. */
    public function install(InstallOptions $options): User
    {
        throw new SetupException('The migration wizard installs the schema only; it never creates the administrator or the installed marker.');
    }

    /**
     * Epesi 3 must never migrate on top of a surviving legacy table of the same
     * name (`modules`, `variables`, ...), and must not run while legacy data is
     * missing. Every mapped legacy table has to exist under its new name; the
     * old name is then either free or already holds an Epesi 3 table (a rerun
     * after an interrupted install).
     *
     * @param  array<string, string>  $renameMap
     */
    protected function assertNamespaceFree(array $renameMap): void
    {
        if ($renameMap === []) {
            throw new SetupException('No legacy table rename map was given.');
        }
        $existing = [];
        foreach ($renameMap as $old => $new) {
            foreach ([(string) $old, (string) $new] as $name) {
                if (Schema::hasTable($name)) {
                    $existing[] = $name;
                }
            }
        }
        $state = (new LegacyTableNamespace)->reconcile($renameMap, $existing);
        if ($state['pending'] !== [] || $state['missing'] !== []) {
            throw new SetupException('The legacy tables are not all renamed ('.count($state['pending']).' still in the Epesi 3 namespace, '.count($state['missing']).' missing). Finish the rename first.');
        }
    }

    protected function migrate(): void
    {
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new SetupException('Creating the Epesi 3 database tables failed.');
        }
    }

    protected function seedRoles(): void
    {
        (new RoleSeeder)->run();
    }
}
