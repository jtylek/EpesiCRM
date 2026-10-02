<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a user left on screen, kept past the session: every list's filters,
 * search, sort, columns and page size (Filament's `tables.*` session keys) and
 * the last page visited. See App\Support\UiState.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ui_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('tables')->nullable();
            $table->text('last_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ui_states');
    }
};
