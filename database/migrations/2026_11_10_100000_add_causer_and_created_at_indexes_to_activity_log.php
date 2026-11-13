<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The User Activity Report reads activity_log by causer and date range, newest
 * first. spatie's table indexes only (causer_type, causer_id) and log_name, so
 * the date filter and sort scanned every row of the user. The composite extends
 * that causer index with created_at; the single created_at index serves reports
 * across all users. Index names are spelled out (64-character limit).
 */
return new class extends Migration
{
    private const INDEXES = [
        'activity_log_causer_created_idx' => ['causer_type', 'causer_id', 'created_at'],
        'activity_log_created_at_idx' => ['created_at'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        foreach (self::INDEXES as $name => $columns) {
            if (! Schema::hasIndex('activity_log', $name)) {
                Schema::table('activity_log', fn (Blueprint $table) => $table->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            if (Schema::hasIndex('activity_log', $name)) {
                Schema::table('activity_log', fn (Blueprint $table) => $table->dropIndex($name));
            }
        }
    }
};
