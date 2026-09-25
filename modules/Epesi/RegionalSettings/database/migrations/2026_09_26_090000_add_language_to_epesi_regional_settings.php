<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The user's language (Epesi's Base_Lang_Administrator "language" user
 * setting); on the row with no user, the system default language. Null
 * means "the system default" for a user, and config('app.locale') for the
 * default row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_regional_settings', function (Blueprint $table) {
            $table->string('language', 8)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_regional_settings', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
