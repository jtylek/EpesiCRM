<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_appearance_themes', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            // Empty means "leave the panel's own colour alone".
            $table->string('accent_color')->nullable();
            // Theme::DENSITY_* — compact (this app's own shipped default) or comfortable
            // (Filament's own spacing).
            $table->string('density')->default('compact');
            // At most one row true at a time (Theme::booted()); what a user
            // with no selection of their own gets.
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_appearance_themes');
    }
};
