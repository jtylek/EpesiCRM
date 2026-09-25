<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The row with no user holds the system-wide defaults — Epesi's
 * Base_User_SettingsCommon::save_admin() values, which the setup wizard sets
 * and every user starts from until they choose their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_regional_settings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('epesi_regional_settings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
