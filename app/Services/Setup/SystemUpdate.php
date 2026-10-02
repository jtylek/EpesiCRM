<?php

namespace App\Services\Setup;

use App\Models\Module;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use App\Support\Optimize\FrameworkCaches;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Brings the database up to date after new files were unpacked over an
 * installation — Epesi's update.php. Covers the core app's migrations and
 * those of every enabled module. The module folders are passed explicitly
 * rather than relying on each module's provider to register them with
 * loadMigrationsFrom(), so a module that doesn't is still covered, and the
 * page can say which module a change belongs to.
 *
 * Used by Administration → Database update, the notice shown to
 * administrators while an update is waiting, and `php artisan epesi:update`.
 */
class SystemUpdate
{
    /** Per request: the notice asks on every page an administrator opens. */
    protected static ?int $pendingCache = null;

    public function __construct(protected Migrator $migrator) {}

    /**
     * Folders holding migrations: the core app's, then each enabled module's.
     *
     * @return array<string, string> label => folder
     */
    public function paths(): array
    {
        $paths = ['epesi' => database_path('migrations')];

        foreach (ModuleRegistry::enabled() as $module) {
            $folder = Module::directoryFor($module['path']).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';

            if (is_dir($folder)) {
                $paths[$module['name'] ?? $module['path']] = $folder;
            }
        }

        return $paths;
    }

    /**
     * Registered modules whose module.json no longer matches their row in the
     * modules table — a new release can change the panels a module registers
     * into, its plugin or its version, and the table (what actually boots)
     * only learns of it from here or from `module:register`.
     *
     * @return array<string, ModuleManifest> module id => the manifest on disk
     */
    public function staleModules(): array
    {
        $stale = [];

        foreach (Module::query()->get() as $module) {
            $file = Module::directoryFor($module->path).DIRECTORY_SEPARATOR.'module.json';

            if (! is_file($file)) {
                continue;
            }

            try {
                $manifest = ModuleManifest::fromJson((string) file_get_contents($file));
            } catch (Throwable) {
                continue;
            }

            foreach ($manifest->toDatabaseRow() as $column => $value) {
                if ($module->{$column} != $value) {
                    $stale[$module->module_id] = $manifest;

                    break;
                }
            }
        }

        return $stale;
    }

    /**
     * Writes the manifests found by staleModules() into the modules table,
     * keeping each module's enabled state. Returns the ids it updated.
     *
     * @return list<string>
     */
    public function syncManifests(): array
    {
        $stale = $this->staleModules();

        foreach ($stale as $id => $manifest) {
            Module::query()->where('module_id', $id)->first()?->update($manifest->toDatabaseRow());
        }

        if ($stale !== []) {
            ModuleRegistry::refresh();

            // Rebuilt by cron in a later process (FrameworkCaches).
            app(FrameworkCaches::class)->forget();

            try {
                Artisan::call('view:clear');
            } catch (Throwable) {
                // A cache that can't be cleared shouldn't fail the update.
            }
        }

        return array_keys($stale);
    }

    /**
     * What an update would do: module registrations that changed, then
     * migrations not run yet — each by where it comes from.
     *
     * @return array<string, list<string>> label => migration names (or module ids)
     */
    public function pending(): array
    {
        $pending = [];

        if ($stale = array_keys($this->staleModules())) {
            $pending[__('Module registrations')] = $stale;
        }

        if (! $this->migrator->repositoryExists()) {
            return $pending;
        }

        $ran = array_flip($this->migrator->getRepository()->getRan());

        foreach ($this->paths() as $label => $folder) {
            $names = array_keys(array_diff_key($this->migrator->getMigrationFiles($folder), $ran));

            if ($names !== []) {
                $pending[$label] = $names;
            }
        }

        return $pending;
    }

    public function pendingCount(): int
    {
        return array_sum(array_map('count', $this->pending()));
    }

    /**
     * pendingCount() for the notice and RedirectToDatabaseUpdate, which ask
     * on every page load: remembered for the request, and in the cache for as
     * long as fingerprint() stays the same. 0 when the database can't be
     * asked (not installed, or unreachable).
     */
    public static function waiting(): int
    {
        try {
            return static::$pendingCache ??= app(static::class)->cachedPendingCount();
        } catch (Throwable) {
            return 0;
        }
    }

    public function cachedPendingCount(): int
    {
        $fingerprint = $this->fingerprint();

        if ($fingerprint === null) {
            return $this->pendingCount();
        }

        return (int) Cache::remember('epesi-system-update:'.$fingerprint, now()->addDay(), fn (): int => $this->pendingCount());
    }

    /**
     * What pending() depends on, for a fraction of its cost: the migration
     * folders' modification times (a new migration file changes its folder's),
     * every registered module's module.json, and the size of the migrations
     * and modules tables. Running a migration adds a row, and syncManifests()
     * moves a module's updated_at, so neither needs to forget anything.
     * Null when the tables can't be read (not installed yet).
     */
    public function fingerprint(): ?string
    {
        try {
            $migrations = DB::table('migrations')->selectRaw('count(*) as total, max(id) as last')->first();
            $modules = DB::table('modules')->orderBy('id')->get(['path', 'updated_at']);
        } catch (Throwable) {
            return null;
        }

        $parts = ['migrations|'.$migrations?->total.'|'.$migrations?->last];

        foreach ($modules as $module) {
            $file = Module::directoryFor($module->path).DIRECTORY_SEPARATOR.'module.json';
            $parts[] = $module->path.'|'.$module->updated_at.'|'.(is_file($file) ? filemtime($file).'|'.filesize($file) : '-');
        }

        foreach ($this->paths() as $folder) {
            $parts[] = $folder.'|'.(@filemtime($folder) ?: '-');
        }

        return hash('xxh128', implode("\n", $parts));
    }

    /**
     * Runs every pending migration in one `migrate`, which orders them by
     * their date-stamped names across all folders — the order they were
     * written in. Returns the migrator's output; throws with it when a
     * migration fails.
     */
    public function run(): string
    {
        // A migration that moves data (files, rows) can outlast PHP's usual
        // 30 seconds.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        static::$pendingCache = null;

        $this->syncManifests();

        $code = Artisan::call('migrate', [
            '--path' => array_values($this->paths()),
            '--realpath' => true,
            '--force' => true,
        ]);

        $output = trim(Artisan::output());

        if ($code !== 0) {
            throw new SetupException($output !== '' ? $output : 'The database update failed.');
        }

        return $output;
    }

    /** Forgets the remembered count, e.g. after run() in the same request. */
    public static function flush(): void
    {
        static::$pendingCache = null;
    }
}
