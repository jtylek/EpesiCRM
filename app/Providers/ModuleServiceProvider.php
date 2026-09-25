<?php

namespace App\Providers;

use App\Models\Module;
use App\Support\Modules\ModuleRegistry;
use Composer\Autoload\ClassLoader;
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
    public function register(): void
    {
        if (! config('modules.load', true)) {
            return;
        }

        $modules = ModuleRegistry::enabled();

        $this->registerAutoloader($modules);

        if ($modules === []) {
            return;
        }

        $this->registerModuleProviders($modules);
        $this->registerTranslations($modules);
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
