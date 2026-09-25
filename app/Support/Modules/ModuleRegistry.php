<?php

namespace App\Support\Modules;

use Filament\Contracts\Plugin;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

/**
 * The list of enabled modules, read during service-provider *registration* —
 * before the application is booted, and on every single request. The DB table
 * (App\Models\Module) is the source of truth; this reads a generated PHP cache
 * file instead so a normal request makes no query at all, the same way Laravel
 * caches its own package discovery.
 *
 * The cache is rewritten by App\Services\Modules\ModuleInstaller on every
 * install/enable/disable/uninstall, and regenerates itself if the file is
 * missing.
 */
class ModuleRegistry
{
    /** @var array<int, array<string, mixed>>|null */
    protected static ?array $modules = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function enabled(): array
    {
        if (static::$modules !== null) {
            return static::$modules;
        }

        if (config('modules.from_manifests')) {
            return static::$modules = static::readManifests();
        }

        $cached = static::readCache();

        if ($cached !== null) {
            return static::$modules = $cached;
        }

        $fromDatabase = static::readDatabase();

        if ($fromDatabase === null) {
            // No table yet (fresh install, mid-migrate) or the database is
            // unreachable. Boot with no modules rather than taking the whole
            // app down, and don't cache that — it isn't a known-good answer.
            return static::$modules = [];
        }

        static::writeCache($fromDatabase);

        return static::$modules = $fromDatabase;
    }

    /**
     * Filament plugin instances for one panel id, for Panel::plugins().
     *
     * @return array<int, Plugin>
     */
    public static function pluginsFor(string $panel): array
    {
        $plugins = [];

        foreach (static::enabled() as $module) {
            $class = $module['plugin'] ?? null;

            if (! $class || ! in_array($panel, $module['panels'] ?? [], true)) {
                continue;
            }

            if (! class_exists($class) || ! is_subclass_of($class, Plugin::class)) {
                continue;
            }

            $plugins[] = method_exists($class, 'make') ? $class::make() : app($class);
        }

        return $plugins;
    }

    /**
     * Re-read the database and rewrite the cache file. Called after every
     * change to the modules table.
     */
    public static function refresh(): void
    {
        // Manifests mode never consults the table, and the cache file belongs
        // to the real install sharing this checkout: writing a test database's
        // empty `modules` table into it would boot that install with no
        // modules, and every later test in the run with none either.
        if (config('modules.from_manifests')) {
            static::$modules = static::readManifests();

            return;
        }

        static::$modules = null;

        $fromDatabase = static::readDatabase() ?? [];

        static::writeCache($fromDatabase);

        static::$modules = $fromDatabase;
    }

    public static function cachePath(): string
    {
        return base_path('bootstrap/cache/epesi-modules.php');
    }

    /**
     * @return array<int, array<string, mixed>>|null null when the cache is absent or unusable
     */
    protected static function readCache(): ?array
    {
        $path = static::cachePath();

        if (! is_file($path)) {
            return null;
        }

        $cached = @include $path;

        return is_array($cached) ? $cached : null;
    }

    /**
     * Every module present under modules/, straight from its module.json,
     * with no database or cache involved — what the test suite boots with
     * (MODULES_FROM_MANIFESTS in phpunit.xml), since an in-memory database
     * has no `modules` rows yet when providers register.
     *
     * Searched recursively rather than globbed at a fixed depth: a module path
     * may nest under grouping directories (Epesi/CRM/Contacts).
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function readManifests(): array
    {
        $path = config('modules.path');
        $files = is_dir($path) ? Finder::create()->files()->in($path)->name('module.json') : [];

        return collect($files)
            ->map(function (SplFileInfo $file): ?array {
                try {
                    $manifest = ModuleManifest::fromJson($file->getContents());
                } catch (Throwable) {
                    return null;
                }

                return [
                    'id' => $manifest->id,
                    'path' => $manifest->path,
                    'namespace' => $manifest->namespace,
                    'provider' => $manifest->providerClass,
                    'plugin' => $manifest->pluginClass,
                    'panels' => $manifest->panels,
                ];
            })
            ->filter()
            ->sortBy('id')
            ->values()
            ->all();
    }

    /**
     * The query builder, not Eloquent: this runs during provider registration,
     * and Model::setConnectionResolver() doesn't happen until
     * DatabaseServiceProvider::boot() — an Eloquent query here fails on a null
     * resolver, which the catch below would then hide.
     *
     * @return array<int, array<string, mixed>>|null null when the table can't be read
     */
    protected static function readDatabase(): ?array
    {
        try {
            return DB::table('modules')
                ->where('enabled', true)
                ->orderBy('module_id')
                ->get(['module_id', 'path', 'namespace', 'provider_class', 'plugin_class', 'panels'])
                ->map(fn (object $row): array => [
                    'id' => $row->module_id,
                    'path' => $row->path,
                    'namespace' => $row->namespace,
                    'provider' => $row->provider_class,
                    'plugin' => $row->plugin_class,
                    'panels' => json_decode((string) $row->panels, true) ?: [],
                ])
                ->all();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $modules
     */
    protected static function writeCache(array $modules): void
    {
        $path = static::cachePath();

        try {
            $temporary = $path.'.'.getmypid().'.tmp';

            file_put_contents($temporary, '<?php return '.var_export($modules, true).';'.PHP_EOL);
            rename($temporary, $path);

            // Read back with include, so OPcache would otherwise keep serving
            // the old list until it next checks the file (every 2 seconds by
            // default) — right after setup, a page without the new modules.
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }
        } catch (Throwable) {
            // A read-only bootstrap/cache just means every request pays for the
            // query instead; not worth failing the request over.
        }
    }
}
