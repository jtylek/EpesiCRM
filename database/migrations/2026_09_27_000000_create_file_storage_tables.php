<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Port of Utils/FileStorage's two tables — see App\Services\FileStorage:
 *
 *   utils_filestorage_files → stored_file_contents (one row per distinct content, by hash)
 *   utils_filestorage       → stored_files         (one row per use: a name for a content)
 *
 * Not ported: utils_filestorage_remote (token links for outside callers) and
 * utils_filestorage_access (the download log) — nothing here uses them yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_file_contents', function (Blueprint $table) {
            $table->id();
            $table->char('hash', 128)->unique();
            $table->unsignedBigInteger('size');
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });

        Schema::create('stored_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('stored_file_contents')->restrictOnDelete();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
        Schema::dropIfExists('stored_file_contents');
    }
};
