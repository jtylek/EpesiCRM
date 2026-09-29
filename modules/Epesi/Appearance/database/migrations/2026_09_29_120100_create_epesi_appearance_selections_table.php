<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's own theme choice — a row is created only once they actually save
 * one (UserAppearance::choose()), not for every user up front. Kept apart
 * from `users` itself, the same way RegionalSettings keeps its own table
 * instead of adding columns there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_appearance_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Null: the user hasn't chosen — Theme::resolveFor() falls back
            // to the default theme. Not the same as "chose to have no theme",
            // which this module doesn't offer.
            $table->foreignId('theme_id')->nullable()->constrained('epesi_appearance_themes')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_appearance_selections');
    }
};
