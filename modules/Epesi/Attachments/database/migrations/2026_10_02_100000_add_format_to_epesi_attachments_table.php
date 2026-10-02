<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the note's body is written in: `html` (every existing note, and what
 * the rich-text editor produces) or `markdown`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_attachments', function (Blueprint $table) {
            $table->string('format', 10)->default('html')->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_attachments', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
