<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Administration → Cron tells a run by cron from one by hand: each task keeps
 * how it was last started (cron_tasks.last_via, as cron_calls.via).
 *
 * And cron_calls.started_at stops moving. Created as a plain NOT NULL
 * timestamp, it was the table's first one, which MySQL and MariaDB give
 * DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP unless
 * explicit_defaults_for_timestamp is on. So finishing a call overwrote its
 * start with the database's own clock, hours off from the app's UTC where
 * the database runs on local time. Nullable, it gets neither. Calls take
 * seconds, so a call's finish is near enough to its start to put back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cron_tasks', function (Blueprint $table) {
            // cli, url, schedule:run, terminal or browser, as cron_calls.via.
            $table->string('last_via', 20)->nullable()->after('last_status');
        });

        Schema::table('cron_calls', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->change();
        });

        DB::table('cron_calls')->whereNotNull('finished_at')->update(['started_at' => DB::raw('finished_at')]);
    }

    public function down(): void
    {
        Schema::table('cron_tasks', function (Blueprint $table) {
            $table->dropColumn('last_via');
        });
    }
};
