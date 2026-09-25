<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers which rows the demo data created (the `demo_records` table), so
 * Administration → Demo data can remove exactly those again: the demo users,
 * companies, contacts, tasks, calls, meetings and shoutbox messages, with
 * everything hanging off them. What the administrator added (their own
 * account, contact and company from setup, and any record since) stays.
 */
class DemoData
{
    /** Deleted in this order, so no row outlives one it points to. */
    protected const ORDER = ['epesi_shoutbox_messages', 'phone_calls', 'tasks', 'meetings', 'contacts', 'companies', 'users'];

    public static function remember(Model $record): void
    {
        DB::table('demo_records')->insertOrIgnore([
            'table_name' => $record->getTable(),
            'record_id' => $record->getKey(),
        ]);
    }

    public static function present(): bool
    {
        return Schema::hasTable('demo_records') && DB::table('demo_records')->exists();
    }

    /**
     * @return array<string, int> table => number of demo rows still there
     */
    public static function counts(): array
    {
        if (! Schema::hasTable('demo_records')) {
            return [];
        }

        return DB::table('demo_records')
            ->selectRaw('table_name, count(*) as n')
            ->groupBy('table_name')
            ->pluck('n', 'table_name')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    /**
     * Removes every demo row for good (not to the trash), with what points to
     * it: pivot rows go with the database's own cascades, and polymorphic
     * rows (history, notifications, roles, notes, watching, reminders) are
     * found in every table that has a `<name>_type`/`<name>_id` pair.
     *
     * @return int rows removed from the demo tables
     */
    public static function remove(): int
    {
        $byTable = DB::table('demo_records')->get()
            ->groupBy('table_name')
            ->map(fn (Collection $rows): array => $rows->pluck('record_id')->map(fn ($id): int => (int) $id)->all());

        if ($byTable->isEmpty()) {
            return 0;
        }

        $tables = collect(static::ORDER)->intersect($byTable->keys())
            ->merge($byTable->keys()->diff(static::ORDER))
            ->values();

        $removed = 0;

        DB::transaction(function () use ($byTable, $tables, &$removed): void {
            static::removePolymorphicRows($byTable->all());

            if ($byTable->has('users') && Schema::hasTable('sessions')) {
                foreach (array_chunk($byTable['users'], 500) as $ids) {
                    DB::table('sessions')->whereIn('user_id', $ids)->delete();
                }
            }

            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                foreach (array_chunk($byTable[$table], 500) as $ids) {
                    $removed += DB::table($table)->whereIn('id', $ids)->delete();
                }
            }

            DB::table('demo_records')->delete();
        });

        return $removed;
    }

    /**
     * @param  array<string, list<int>>  $byTable
     */
    protected static function removePolymorphicRows(array $byTable): void
    {
        // Morph alias for each demo table: "company" for companies, etc.
        $aliases = [];

        foreach (Relation::morphMap() as $alias => $class) {
            if (class_exists($class) && is_subclass_of($class, Model::class)) {
                $table = (new $class)->getTable();

                if (isset($byTable[$table])) {
                    $aliases[$alias] = $byTable[$table];
                }
            }
        }

        if ($aliases === []) {
            return;
        }

        // This connection's own schema only: on MySQL, getTables() lists every
        // database on the server, the legacy epesi one included.
        foreach (Schema::getTableListing(Schema::getCurrentSchemaListing(), schemaQualified: false) as $table) {

            if ($table === 'demo_records') {
                continue;
            }

            $columns = array_flip(Schema::getColumnListing($table));

            foreach (array_keys($columns) as $column) {
                if (! str_ends_with($column, '_type') || ! isset($columns[$id = substr($column, 0, -5).'_id'])) {
                    continue;
                }

                foreach ($aliases as $alias => $ids) {
                    foreach (array_chunk($ids, 500) as $chunk) {
                        DB::table($table)->where($column, $alias)->whereIn($id, $chunk)->delete();
                    }
                }
            }
        }
    }
}
