<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's own small notes: a title, a Markdown body, a background color, and
 * whether the note is still active. Inactive ones are kept until deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_sticky_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('body')->nullable();
            $table->string('color', 16)->default('yellow');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'active'], 'epesi_sticky_notes_user_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_sticky_notes');
    }
};
