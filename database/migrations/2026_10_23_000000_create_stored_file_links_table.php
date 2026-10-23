<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Images pasted into a rich-text editor are StoredFiles; this says which
 * records' text holds each one (App\Support\Files\InlineImages), so the image
 * route can ask whether the viewer may see one of them. A row without an
 * owner is an upload not saved with a record yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_file_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stored_file_id')->constrained('stored_files')->cascadeOnDelete();
            $table->nullableMorphs('owner');
            $table->timestamps();

            $table->unique(['stored_file_id', 'owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_file_links');
    }
};
