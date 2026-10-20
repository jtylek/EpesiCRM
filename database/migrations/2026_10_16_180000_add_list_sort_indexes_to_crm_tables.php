<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each CRM list opens sorted by one column (CompanyResource::$recordsetDefaultSort
 * and its siblings) over rows that are not soft-deleted, and none of those
 * columns was indexed, so every list page, search and page change sorted the
 * whole table. At 100,000 companies that is about 120 ms of database time per
 * request against about 1 ms with the index; it grows with the table.
 *
 * `deleted_at` leads so the `deleted_at IS NULL` every model adds is a prefix
 * of the index and the rows come out already in sort order. Index names are
 * spelled out, as the 64-character limit applies to generated ones.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: list<string>}> table => [index name, columns after deleted_at] */
    private const INDEXES = [
        'companies' => ['companies_list_sort_index', ['company_name']],
        'contacts' => ['contacts_list_sort_index', ['last_name', 'first_name']],
        'tasks' => ['tasks_list_sort_index', ['deadline']],
        'meetings' => ['meetings_list_sort_index', ['date']],
        'phone_calls' => ['phone_calls_list_sort_index', ['called_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => [$name, $columns]) {
            if (! Schema::hasTable($table) || Schema::hasIndex($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name, $columns) {
                $blueprint->index(['deleted_at', ...$columns], $name);
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => [$name]) {
            if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
            }
        }
    }
};
