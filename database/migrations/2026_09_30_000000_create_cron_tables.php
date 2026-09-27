<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What Administration → Cron shows, Epesi's `cron` table: when each
 * scheduled task last ran and how that went, and when cron itself was last
 * called. App\Services\Cron\CronLog writes both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_tasks', function (Blueprint $table) {
            $table->id();
            // sha1 of the task's mutex name, which is its schedule and command.
            $table->string('key', 40)->unique();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->float('last_duration')->nullable();
            // running, ok or failed.
            $table->string('last_status', 20)->nullable();
            $table->text('last_output')->nullable();
            // created_at: when cron first saw the task.
            $table->timestamps();
        });

        Schema::create('cron_calls', function (Blueprint $table) {
            $table->id();
            // cli (cron.php), url, browser or schedule:run.
            $table->string('via', 20);
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedSmallInteger('tasks')->default(0);
            $table->unsignedSmallInteger('failed')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_calls');
        Schema::dropIfExists('cron_tasks');
    }
};
