<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy base_dashboard_tabs, and an applet as a row of its own rather than
 * one place per widget class: a user may put the same applet on the
 * dashboard more than once, each copy with its own settings
 * (base_dashboard_applets + base_dashboard_settings).
 *
 * The table is rebuilt rather than altered: the old one's unique
 * (user_id, widget) index may be the one MySQL uses for the user_id foreign
 * key, and then it can't be dropped. The arrangements it held are a day's
 * worth of drag and drop; everyone gets the default dashboard again, with
 * the applets added since.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_tabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            $table->unsignedSmallInteger('pos');
        });

        Schema::drop('dashboard_applets');

        Schema::create('dashboard_applets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dashboard_tab_id')->constrained()->cascadeOnDelete();
            $table->string('widget');
            $table->unsignedTinyInteger('col');
            $table->unsignedSmallInteger('pos');
            $table->json('settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::drop('dashboard_applets');
        Schema::drop('dashboard_tabs');

        Schema::create('dashboard_applets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('widget');
            $table->unsignedTinyInteger('col');
            $table->unsignedSmallInteger('pos');
            $table->unique(['user_id', 'widget']);
        });
    }
};
