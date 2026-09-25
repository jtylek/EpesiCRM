<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Port of Utils/Watchdog's tables. `utils_watchdog_event` has no counterpart:
 * every change is already an `activity_log` row, so a subscription just
 * remembers the last of those its user has seen. `utils_watchdog_category`
 * is likewise gone — a category is a record type's morph alias.
 *
 * `notifications` is Laravel's own table, which Filament's bell reads; it is
 * created here only if nothing else has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_watchdog_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Named explicitly: Laravel's generated name is 67 characters,
            // past MySQL's 64-character identifier limit.
            $table->morphs('subscribable', 'epesi_watchdog_subscriptions_subscribable_index');
            $table->unsignedBigInteger('last_seen_activity_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'subscribable_type', 'subscribable_id'], 'epesi_watchdog_subscriptions_unique');
        });

        Schema::create('epesi_watchdog_category_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 64);
            $table->timestamps();

            $table->unique(['user_id', 'category']);
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_watchdog_category_subscriptions');
        Schema::dropIfExists('epesi_watchdog_subscriptions');
    }
};
