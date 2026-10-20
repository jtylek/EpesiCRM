<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the store said it is on its last answer (its X-Epesi-Store-Environment
 * header): 'production', 'test', or null for a store too old to say. A test
 * store puts a warning bar on the administration panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_store_settings', function (Blueprint $table) {
            $table->string('store_environment', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('epesi_store_settings', function (Blueprint $table) {
            $table->dropColumn('store_environment');
        });
    }
};
