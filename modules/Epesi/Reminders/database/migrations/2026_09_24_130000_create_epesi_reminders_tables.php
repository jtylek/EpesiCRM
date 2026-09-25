<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Port of Utils/Messenger's `utils_messenger_message` and
 * `utils_messenger_users`.
 *
 * Epesi keyed an alert to its record through an md5 "page id" plus a
 * serialized callback that rendered the popup text; here the record is a
 * plain polymorphic relation and the text is built from it at delivery time.
 *
 * `before_minutes` is new: Epesi stored only the alert time and, when a
 * meeting moved, shifted every alert on it by the same amount. Keeping the
 * offset lets a "30 minutes before" reminder follow its record while one set
 * for a fixed date and time (before_minutes null) stays put.
 *
 * Per recipient, `sent_at` is Epesi's `follow` flag (the cron has delivered
 * it) and `dismissed_at` its `done` / `done_on` (the user turned it off).
 * `notifications` (Laravel's table, which Filament's bell reads) is created
 * only if nothing else has, as the Watchdog module does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_reminders', function (Blueprint $table) {
            $table->id();
            $table->morphs('remindable');
            $table->dateTime('remind_at');
            $table->unsignedInteger('before_minutes')->nullable();
            $table->text('message')->nullable();
            $table->boolean('send_email')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('remind_at');
        });

        Schema::create('epesi_reminder_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained('epesi_reminders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['reminder_id', 'user_id']);
            $table->index(['user_id', 'sent_at']);
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
        Schema::dropIfExists('epesi_reminder_recipients');
        Schema::dropIfExists('epesi_reminders');
    }
};
