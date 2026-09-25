<?php

namespace App\Services\Setup;

use App\Models\Module;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
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
     * Migrations not run yet, by where they come from.
     *
     * @return array<string, list<string>> label => migration names
     */
    public function pending(): array
    {
        if (! $this->migrator->repositoryExists()) {
            return [];
        }

        $ran = array_flip($this->migrator->getRepository()->getRan());
        $pending = [];

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
     * pendingCount() for the notice: remembered for the request, and 0 when
     * the database can't be asked (not installed, or unreachable).
     */
    public static function waiting(): int
    {
        try {
            return static::$pendingCache ??= app(static::class)->pendingCount();
        } catch (Throwable) {
            return 0;
        }
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
