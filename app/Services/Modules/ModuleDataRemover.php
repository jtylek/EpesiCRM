<?php

namespace App\Services\Modules;

use App\Models\Module;
use App\Models\StoredFile;
use App\Support\Files\StoredFileReferences;
use Composer\Autoload\ClassLoader;
use Epesi\Modules\CommonData\CommonDataRepository;
use Epesi\Modules\RecordBrowser\Files\StoredFileIds;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Puts the database back the way it was before a module was installed, as
 * far as that can be known:
 *
 * - what its migrations created: they are rolled back, and the tables and
 *   seeded rows ModuleFootprint recorded are dropped and deleted after that,
 *   in case a `down()` left something behind (or does nothing at all);
 * - what using it created elsewhere: every row in a shared table that points
 *   at one of its records through a `*_type` morph column (history, links,
 *   collection items, favourites, reminders, notes' links, …), notes left
 *   attached to nothing, its Shield permissions and dashboard applets, and
 *   the stored files nothing refers to any more.
 *
 * What it cannot undo: a migration's changes to rows that existed before
 * (an UPDATE leaves no footprint), and data in places that name a record
 * without a morph alias.
 */
class ModuleDataRemover
{
    /** @var array<int, string> */
    protected array $warnings = [];

    /**
     * @return array<int, string> what could not be removed, for the administrator
     */
    public function remove(Module $module): array
    {
        $this->warnings = [];

        $this->load($module);

        $aliases = $this->aliases($module);
        $fileIds = $this->fileIds($aliases);

        if ($aliases !== []) {
            $fileIds = [...$fileIds, ...$this->removeNotes($aliases)];
            $this->removeMorphRows($aliases);
            $this->removePermissions($module, $aliases);
        }

        $this->removeClassRows($module);
        $this->rollBack($module);
        $this->dropTables($module);
        $this->deleteRows($module);
        $this->forgetMigrations($module);
        $this->deleteOrphanedFiles($fileIds);

        ModuleFootprint::forget($module->module_id);

        if (class_exists(CommonDataRepository::class)) {
            CommonDataRepository::invalidate();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->warnings;
    }

    /**
     * A disabled module's classes aren't autoloaded and its provider never
     * registered its morph aliases: both are needed to find its records.
     */
    protected function load(Module $module): void
    {
        if ($module->namespace && ! $this->isLoaded($module)) {
            $loader = new ClassLoader;
            $loader->addPsr4($module->namespace, $module->directory().DIRECTORY_SEPARATOR.'src');
            $loader->register();
        }

        if ($module->provider_class && class_exists($module->provider_class) && ! app()->getProvider($module->provider_class)) {
            try {
                app()->register($module->provider_class);
            } catch (Throwable $exception) {
                $this->warnings[] = "The module's service provider failed to load, so rows pointing at its records may remain: {$exception->getMessage()}";
            }
        }
    }

    protected function isLoaded(Module $module): bool
    {
        return $module->provider_class !== null && class_exists($module->provider_class);
    }

    /**
     * The morph aliases of the module's own models.
     *
     * @return array<string, class-string> alias => class
     */
    protected function aliases(Module $module): array
    {
        if (! $module->namespace) {
            return [];
        }

        return collect(Relation::morphMap())
            ->filter(fn (string $class): bool => str_starts_with($class, $module->namespace))
            ->all();
    }

    /**
     * Files the module's records hold, collected while their tables still
     * exist. Deleted at the end only if nothing else refers to them.
     *
     * @param  array<string, class-string>  $aliases
     * @return array<int, int>
     */
    protected function fileIds(array $aliases): array
    {
        $ids = [];

        foreach ($aliases as $class) {
            if (! is_subclass_of($class, Model::class) || ! method_exists($class, 'fileColumns')) {
                continue;
            }

            $table = (new $class)->getTable();

            foreach ((new $class)->fileColumns() as $column) {
                if (Schema::hasColumn($table, $column)) {
                    foreach (DB::table($table)->whereNotNull($column)->pluck($column) as $raw) {
                        array_push($ids, ...StoredFileIds::decode($raw));
                    }
                }
            }
        }

        if ($aliases !== [] && Schema::hasTable('stored_file_links')) {
            array_push($ids, ...DB::table('stored_file_links')->whereIn('owner_type', array_keys($aliases))->pluck('stored_file_id')->all());
        }

        return array_map('intval', $ids);
    }

    /**
     * Notes attached only to the module's records: once their links go they
     * belong to nothing and no screen shows them.
     *
     * @param  array<string, class-string>  $aliases
     * @return array<int, int> the files those notes held
     */
    protected function removeNotes(array $aliases): array
    {
        if (! Schema::hasTable('epesi_attachments') || ! Schema::hasTable('epesi_attachment_links')) {
            return [];
        }

        $linked = DB::table('epesi_attachment_links')->whereIn('attachable_type', array_keys($aliases))->pluck('attachment_id')->unique();

        if ($linked->isEmpty()) {
            return [];
        }

        $elsewhere = DB::table('epesi_attachment_links')->whereIn('attachment_id', $linked)->whereNotIn('attachable_type', array_keys($aliases))->pluck('attachment_id');
        $orphans = $linked->diff($elsewhere)->values();

        $files = [];

        foreach (DB::table('epesi_attachments')->whereIn('id', $orphans)->whereNotNull('files')->pluck('files') as $raw) {
            array_push($files, ...StoredFileIds::decode($raw));
        }

        $noteAlias = collect(Relation::morphMap())->search(fn (string $class): bool => is_subclass_of($class, Model::class) && (new $class)->getTable() === 'epesi_attachments');

        if ($noteAlias !== false && Schema::hasTable('activity_log')) {
            DB::table('activity_log')->where('subject_type', $noteAlias)->whereIn('subject_id', $orphans)->delete();
        }

        DB::table('epesi_attachments')->whereIn('id', $orphans)->delete();

        return array_map('intval', $files);
    }

    /**
     * Every row, in any table, that points at one of the module's records
     * through a morph pair (`x_type` with `x_id`): history, record links,
     * collection items, favourites, recent, reminders, watchdog subscriptions,
     * priority list entries, notes' links, … — plus the recordset-wide rows in
     * `modules.recordset_type_columns` (field overrides, custom field
     * definitions, …), which name a recordset without a record. A lone
     * `*_type` column is not trusted: `document_type = 'invoice'` is no morph.
     *
     * @param  array<string, class-string>  $aliases
     */
    protected function removeMorphRows(array $aliases): void
    {
        $recordsetColumns = (array) config('modules.recordset_type_columns', []);

        foreach (ModuleFootprint::tables() as $table) {
            if (in_array($table, ['migrations', ModuleFootprint::TABLE], true)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);

            foreach ($columns as $column) {
                $pair = str_ends_with($column, '_type') && in_array(substr($column, 0, -5).'_id', $columns, true);

                if (! $pair && ! in_array("{$table}.{$column}", $recordsetColumns, true)) {
                    continue;
                }

                try {
                    DB::table($table)->whereIn($column, array_keys($aliases))->delete();
                } catch (Throwable $exception) {
                    $this->warnings[] = "Rows in {$table} could not be deleted: {$exception->getMessage()}";
                }
            }
        }
    }

    /**
     * Shield names a resource's permissions `Action:ModelBasename`. A basename
     * another module's model shares is left alone: Shield can't tell the two
     * apart either.
     *
     * @param  array<string, class-string>  $aliases
     */
    protected function removePermissions(Module $module, array $aliases): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $others = collect(Relation::morphMap())
            ->reject(fn (string $class): bool => str_starts_with($class, (string) $module->namespace))
            ->map(fn (string $class): string => class_basename($class))
            ->all();

        $basenames = collect($aliases)->map(fn (string $class): string => class_basename($class))
            ->reject(fn (string $basename): bool => in_array($basename, $others, true))
            ->values();

        $ids = DB::table('permissions')->pluck('name', 'id')
            ->filter(fn (string $name): bool => $basenames->contains(fn (string $basename): bool => str_ends_with($name, ':'.$basename)))
            ->keys();

        if ($ids->isEmpty()) {
            return;
        }

        foreach (['role_has_permissions', 'model_has_permissions'] as $pivot) {
            if (Schema::hasTable($pivot)) {
                DB::table($pivot)->whereIn('permission_id', $ids)->delete();
            }
        }

        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    /** Rows that name one of the module's classes, such as a dashboard applet's widget. */
    protected function removeClassRows(Module $module): void
    {
        if (! $module->namespace) {
            return;
        }

        foreach ((array) config('modules.class_columns', []) as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $ids = DB::table($table)->pluck($column, 'id')
                ->filter(fn ($class): bool => is_string($class) && str_starts_with(ltrim($class, '\\'), $module->namespace))
                ->keys();

            if ($ids->isNotEmpty()) {
                DB::table($table)->whereIn('id', $ids)->delete();
            }
        }
    }

    /** The module's own `down()` methods, newest first; anything they miss is caught below. */
    protected function rollBack(Module $module): void
    {
        $path = $this->migrationsPath($module);

        if ($path === null) {
            return;
        }

        try {
            Artisan::call('migrate:reset', ['--path' => $path, '--realpath' => true, '--force' => true]);
        } catch (Throwable $exception) {
            $this->warnings[] = "Rolling back the module's migrations failed, its recorded tables were dropped instead: {$exception->getMessage()}";
        }
    }

    protected function dropTables(Module $module): void
    {
        $tables = array_reverse(ModuleFootprint::tablesOf($module->module_id));

        if ($tables === []) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                try {
                    Schema::dropIfExists($table);
                } catch (Throwable $exception) {
                    $this->warnings[] = "The table {$table} could not be dropped: {$exception->getMessage()}";
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * The rows its migrations seeded into shared tables, with whatever an
     * administrator added beneath them later (a CommonData list's options).
     */
    protected function deleteRows(Module $module): void
    {
        foreach (ModuleFootprint::rowsOf($module->module_id) as $table => $ids) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'parent_id')) {
                for ($level = $ids; $level !== [];) {
                    $level = DB::table($table)->whereIn('parent_id', $level)->whereNotIn('id', $ids)->pluck('id')->map(fn ($id): int => (int) $id)->all();
                    $ids = [...$ids, ...$level];
                }
            }

            foreach (array_chunk(array_reverse($ids), 500) as $chunk) {
                DB::table($table)->whereIn('id', $chunk)->delete();
            }
        }
    }

    /** A migration whose `down()` failed keeps its record; without it a reinstall would skip it. */
    protected function forgetMigrations(Module $module): void
    {
        $path = $this->migrationsPath($module);

        if ($path === null || ! Schema::hasTable('migrations')) {
            return;
        }

        $names = array_map(fn (string $file): string => pathinfo($file, PATHINFO_FILENAME), glob($path.DIRECTORY_SEPARATOR.'*.php') ?: []);

        if ($names !== []) {
            DB::table('migrations')->whereIn('migration', $names)->delete();
        }
    }

    /** @param  array<int, int>  $candidates */
    protected function deleteOrphanedFiles(array $candidates): void
    {
        $orphans = array_diff(array_unique($candidates), StoredFileReferences::ids());

        foreach (array_chunk($orphans, 500) as $chunk) {
            StoredFile::query()->whereKey($chunk)->get()->each->delete();
        }
    }

    protected function migrationsPath(Module $module): ?string
    {
        $path = $module->directory().DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';

        return is_dir($path) ? $path : null;
    }
}
