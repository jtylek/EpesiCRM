<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One global row (AppearanceSetting::current(), id 1 by convention — no
 * per-user or per-theme variation, unlike Theme/UserAppearance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_appearance_settings', function (Blueprint $table): void {
            $table->id();
            // Empty means "epesi", the shipped brand name.
            $table->string('app_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_appearance_settings');
    }
};
