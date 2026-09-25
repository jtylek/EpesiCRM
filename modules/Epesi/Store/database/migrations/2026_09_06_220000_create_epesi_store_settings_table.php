<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_store_settings', function (Blueprint $table) {
            $table->id();
            $table->string('catalog_url')->nullable();
            $table->string('licence_key')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_store_settings');
    }
};
