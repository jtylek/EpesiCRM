<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The currency a new amount starts in (legacy's per-user default_currency
 * setting); on the row with no user, the system default. Null means "the
 * system default" for a user, and the home currency for the default row
 * (CurrencyRepository::defaultCode()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_regional_settings', function (Blueprint $table) {
            $table->char('currency', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('epesi_regional_settings', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
