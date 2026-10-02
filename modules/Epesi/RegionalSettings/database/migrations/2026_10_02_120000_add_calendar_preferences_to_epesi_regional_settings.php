<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_regional_settings', function (Blueprint $table) {
            $table->string('calendar_system', 16)->default('gregorian');
            $table->string('hijri_variant', 16)->default('umalqura');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_regional_settings', function (Blueprint $table) {
            $table->dropColumn(['calendar_system', 'hijri_variant']);
        });
    }
};