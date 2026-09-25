<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Port of Epesi's per-recordset `<tab>_favorite` and `<tab>_recent` tables,
 * and of the `<tab>_default_view` user setting that remembered which of All,
 * Favorites and Recent a list was last shown in. One table each, keyed by the
 * record's morph alias, the way `custom_fields` replaces `<tab>_field`.
 *
 * Index names are given explicitly: the generated ones run past MySQL's
 * 64-character limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_recordbrowser_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('recordable_type', 64);
            $table->unsignedBigInteger('recordable_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'recordable_type', 'recordable_id'], 'epesi_recordbrowser_favorites_unique');
        });

        Schema::create('epesi_recordbrowser_recent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('recordable_type', 64);
            $table->unsignedBigInteger('recordable_id');
            $table->timestamp('visited_at');

            $table->unique(['user_id', 'recordable_type', 'recordable_id'], 'epesi_recordbrowser_recent_unique');
            $table->index(['user_id', 'recordable_type', 'visited_at'], 'epesi_recordbrowser_recent_visited_index');
        });

        Schema::create('epesi_recordbrowser_browse_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('recordable_type', 64);
            $table->string('mode', 16);
            $table->timestamps();

            $table->unique(['user_id', 'recordable_type'], 'epesi_recordbrowser_browse_modes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_recordbrowser_browse_modes');
        Schema::dropIfExists('epesi_recordbrowser_recent');
        Schema::dropIfExists('epesi_recordbrowser_favorites');
    }
};
