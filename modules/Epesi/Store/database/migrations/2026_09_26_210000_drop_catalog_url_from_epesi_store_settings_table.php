<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_store_settings', function (Blueprint $table) {
            $table->dropColumn('catalog_url');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_store_settings', function (Blueprint $table) {
            $table->string('catalog_url')->nullable()->after('id');
        });
    }
};
