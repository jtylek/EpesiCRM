<?php

namespace App\Providers;

use App\Models\Module;
use App\Services\LegacyImport\ImporterRegistry;
use App\Support\Modules\DeclaredLegacyImporters;
use App\Support\Modules\ModuleRegistry;
use App\Support\Setup\LegacyBootDetector;
use App\Support\Setup\SetupState;
use Composer\Autoload\ClassLoader;
use Epesi\Modules\MigrationWizard\Detection\LegacyDatabaseConnection;
use Epesi\Modules\MigrationWizard\MigrationWizardServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Makes zip-installed modules loadable. Registered *first* in
 * bootstrap/providers.php, because the panel providers configure their panels
 * during register() — a module's Filament resources have to be autoloadable
 * before MainPanelProvider runs, or discovery silently finds nothing.
 *
 * PSR-4 is registered at runtime rather than through composer.json: a module
 * arrives as an extracted directory with no composer step involved, so there is
 * nothing to `dump-autoload`.
 */
class ModuleServiceProvider extends ServiceProvider
{
    private ?bool $wizardActive = null;

    public function register(): void
    {
        if (! config('modules.load', true)) {
            return;
        }

        $modules = ModuleRegistry::enabled();

        // First: detecting the wizard looks for its class, which only the core namespaces make loadable.
        $this->registerAutoloader($modules);

        // The shipped core modules a still-legacy site is about to get are autoloadable (their legacy
        // importers are classes the scan instantiates) but their providers stay unloaded: nothing of
        // them may boot against the legacy database.
        $legacySiteModules = $this->legacyWizardActive($modules) ? DeclaredLegacyImporters::shippedCoreModules() : [];

        if ($legacySiteModules !== []) {
            $this->registerAutoloader($legacySiteModules);
        }

        if ($modules !== []) {
            $this->registerModuleProviders($modules);
            $this->registerTranslations($modules);
        }

        $this->registerLegacyImporters([...$modules, ...$legacySiteModules]);
        $this->registerMigrationWizardForLegacySite($modules);
    }

    /**
     * `import:legacy` importers come from the modules' module.json (DeclaredLegacyImporters), not from
     * their providers, so the Migration Wizard sees them before the modules are installed and the
     * browser run sees the same set as the CLI. Registered when the registry is first resolved: no
     * manifest is read on a request that never asks for the importers.
     *
     * @param  array<int, array<string, mixed>>  $modules
     */
    protected function registerLegacyImporters(array $modules): void
    {
        $this->app->resolving(ImporterRegistry::class, function (ImporterRegistry $registry) use ($modules): void {
            DeclaredLegacyImporters::register($registry, $modules);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $modules
     */
    protected function legacyWizardActive(array $modules): bool
    {
        return $this->wizardActive ??= $this->detectLegacyWizard($modules);
    }

    /**
     * @param  array<int, array<string, mixed>>  $modules
     */
    private function detectLegacyWizard(array $modules): bool
    {
        $provider = MigrationWizardServiceProvider::class;

        if (in_array($provider, array_column($modules, 'provider'), true) || ! class_exists($provider)) {
            return false;
        }

        return LegacyBootDetector::detect($this->app->basePath()) === 'legacy' && ! SetupState::isInstalled();
    }

    /**
     * A folder still holding a legacy Epesi has a `modules` table of its own (name/version
     * columns), which the registry cannot read, so the Migration Wizard never comes from it:
     * without its provider the `epesi:migrate-legacy` command, the views, the config and the
     * importers are missing and the assessment finds no approved schema. Register it here,
     * after the autoloader (a provider listed in bootstrap/providers.php is dropped by
     * Laravel's class_exists() filter, which runs before any module namespace is mapped).
     *
     * Only when a legacy installation is detected, Epesi 3 is not installed yet and the
     * registry did not supply the provider itself; Application::register() is idempotent, so
     * a later registry entry (after the migration installs Epesi 3) does not register it twice.
     */
    protected function registerMigrationWizardForLegacySite(array $modules): void
    {
        if (! $this->legacyWizardActive($modules)) {
            return;
        }

        $this->app->register(MigrationWizardServiceProvider::class);

        // Epesi 3 files unpacked over the old folder come with a default .env naming a SQLite file that
        // does not exist: the application database is the legacy one, from data/config.php. Config only,
        // no connection is opened here.
        LegacyDatabaseConnection::apply($this->app->basePath());
    }

    /**
     * Each module's lang/<code>.json, merged into the application's own —
     * Base_LangCommon::build_merge() walking every module's lang directory.
     * Keys are the English text, so a module needs no namespace for them.
     *
     * @param  array<int, array<string, mixed>>  $modules
     */
    protected function registerTranslations(array $modules): void
    {
        $paths = collect($modules)
            ->map(fn (array $module): string => Module::directoryFor($module['path']).DIRECTORY_SEPARATOR.'lang')
            ->filter(fn (string $path): bool => is_dir($path))
            ->values();

        if ($paths->isNotEmpty()) {
            $this->callAfterResolving('translator', function ($translator) use ($paths): void {
                $paths->each(fn (string $path) => $translator->addJsonPath($path));
            });
        }
    }

    /**
     * A second, dedicated ClassLoader rather than the application's own: getting
     * hold of that one means either re-running vendor/autoload.php for its
     * return value or reaching into Composer's hashed init class. Registering
     * another loader costs a lookup and couples to nothing.
     *
     * @param  array<int, array<string, mixed>>  $modules
     */
    protected function registerAutoloader(array $modules): void
    {
        $loader = new ClassLoader;

        // Core modules first — see config('modules.core_namespaces') for why
        // they don't wait for the registry. A registry entry for the same
        // prefix simply re-adds the same directory.
        $mappings = (array) config('modules.core_namespaces', []);

        foreach ($modules as $module) {
            $mappings[$module['namespace']] = $module['path'];
        }

        foreach ($mappings as $namespace => $path) {
            $loader->addPsr4(
                $namespace,
                Module::directoryFor($path).DIRECTORY_SEPARATOR.'src',
            );
        }

        $loader->register();
    }

    /**
     * @param  array<int, array<string, mixed>>  $modules
     */
    protected function registerModuleProviders(array $modules): void
    {
        foreach ($modules as $module) {
            $provider = $module['provider'] ?? null;

            if ($provider && class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }
}
