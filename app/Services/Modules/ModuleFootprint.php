<?php

namespace App\Services\Modules;

use App\Support\Modules\ModuleManifest;
use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Throwable;

/**
 * Records what each module migration creates, so uninstalling a module can
 * put the database back the way it was before the module was installed.
 *
 * Listens to every migration rather than only to ModuleInstaller's runs: a
 * module's later migrations arrive with an update and run through
 * `epesi:update` or a plain `migrate`, and they create tables and lists too.
 *
 * Two kinds of footprint: a table the migration created, and a row it added
 * to one of the shared tables in `modules.footprint_rows` (the CommonData
 * lists a module seeds). Rows are tracked only for those tables: a migration's
 * new ids in a busy table such as `activity_log` could include rows a user
 * wrote at the same moment.
 */
class ModuleFootprint
{
    public const TABLE = 'module_footprints';

    /** @var array{module: string, tables: array<int, string>, rows: array<string, int>}|null */
    protected ?array $pending = null;

    /** @var array<string, string|null> module directory => module id */
    protected array $modules = [];

    /**
     * Records waiting for the footprint table: on a fresh install, module
     * migrations dated before it run before it exists.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $unsaved = [];

    public function started(MigrationStarted $event): void
    {
        $this->pending = null;

        if ($event->method !== 'up' || ($module = $this->moduleOf($event->migration)) === null) {
            return;
        }

        try {
            $this->pending = ['module' => $module, 'tables' => self::tables(), 'rows' => $this->lastIds()];
        } catch (Throwable) {
            // A footprint is best effort; it must never stop a migration.
        }
    }

    public function ended(MigrationEnded $event): void
    {
        $pending = $this->pending;
        $this->pending = null;

        if ($pending === null || $event->method !== 'up') {
            $this->flush();

            return;
        }

        try {
            $migration = $this->migrationName($event->migration);
            $now = now();
            $records = [];

            foreach (array_diff(self::tables(), $pending['tables']) as $table) {
                $records[] = ['module_id' => $pending['module'], 'kind' => 'table', 'table_name' => $table, 'row_id' => null, 'migration' => $migration, 'created_at' => $now];
            }

            foreach ($pending['rows'] as $table => $lastId) {
                foreach (DB::table($table)->where('id', '>', $lastId)->pluck('id') as $id) {
                    $records[] = ['module_id' => $pending['module'], 'kind' => 'row', 'table_name' => $table, 'row_id' => $id, 'migration' => $migration, 'created_at' => $now];
                }
            }

            $this->unsaved = [...$this->unsaved, ...$records];
            $this->flush();
        } catch (Throwable) {
            // Best effort, as above.
        }
    }

    /** Also called once a whole `migrate` run has finished. */
    public function flush(): void
    {
        if ($this->unsaved === []) {
            return;
        }

        try {
            if (! Schema::hasTable(self::TABLE)) {
                return;
            }

            foreach (array_chunk($this->unsaved, 500) as $chunk) {
                DB::table(self::TABLE)->insert($chunk);
            }

            $this->unsaved = [];
        } catch (Throwable) {
            // Best effort, as above.
        }
    }

    /** @return array<int, string> */
    public static function tablesOf(string $module): array
    {
        return self::query($module, 'table')->pluck('table_name')->unique()->values()->all();
    }

    /** @return array<string, array<int, int>> table => row ids */
    public static function rowsOf(string $module): array
    {
        return self::query($module, 'row')->get(['table_name', 'row_id'])
            ->groupBy('table_name')
            ->map(fn ($rows): array => $rows->pluck('row_id')->map(fn ($id): int => (int) $id)->all())
            ->all();
    }

    public static function forget(string $module): void
    {
        if (Schema::hasTable(self::TABLE)) {
            DB::table(self::TABLE)->where('module_id', $module)->delete();
        }
    }

    protected static function query(string $module, string $kind)
    {
        return Schema::hasTable(self::TABLE)
            ? DB::table(self::TABLE)->where('module_id', $module)->where('kind', $kind)
            : DB::table(self::TABLE)->whereRaw('1 = 0');
    }

    /** The module whose migrations directory holds this migration's file. */
    protected function moduleOf(object $migration): ?string
    {
        $file = (new ReflectionClass($migration))->getFileName();
        $root = realpath((string) config('modules.path'));

        if (! $file || ! $root || ! str_starts_with((string) realpath($file), $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        // <module>/database/migrations/<file>.php
        $directory = dirname($file, 3);

        if (! array_key_exists($directory, $this->modules)) {
            $manifest = $directory.DIRECTORY_SEPARATOR.'module.json';

            try {
                $this->modules[$directory] = is_file($manifest) ? ModuleManifest::fromJson((string) file_get_contents($manifest))->id : null;
            } catch (Throwable) {
                $this->modules[$directory] = null;
            }
        }

        return $this->modules[$directory];
    }

    protected function migrationName(object $migration): ?string
    {
        $file = (new ReflectionClass($migration))->getFileName();

        return $file ? pathinfo($file, PATHINFO_FILENAME) : null;
    }

    /**
     * This database's tables. Without a schema, MySQL lists every database
     * the connection can see.
     *
     * @return array<int, string>
     */
    public static function tables(): array
    {
        return collect(Schema::getTables(Schema::getCurrentSchemaName()))->pluck('name')->all();
    }

    /** @return array<string, int> */
    protected function lastIds(): array
    {
        $ids = [];

        foreach ((array) config('modules.footprint_rows', []) as $table) {
            if (Schema::hasTable($table)) {
                $ids[$table] = (int) DB::table($table)->max('id');
            }
        }

        return $ids;
    }
}
