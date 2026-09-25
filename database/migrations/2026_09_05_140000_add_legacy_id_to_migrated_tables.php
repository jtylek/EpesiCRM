<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * legacy_id records which row of the legacy Epesi database (user_login.id,
     * or <tab>_data_1.id for the RecordBrowser-backed tables) a row was
     * imported from, for `php artisan import:legacy`'s upsert-by-legacy_id
     * and its old-id -> new-id lookups during edit-history reconstruction.
     * A column per table rather than a separate mapping table, since history
     * replay does one such lookup per changed field per historical edit.
     */
    protected array $tables = ['users', 'companies', 'contacts', 'phone_calls', 'tasks', 'meetings'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('legacy_id');
            });
        }
    }
};
