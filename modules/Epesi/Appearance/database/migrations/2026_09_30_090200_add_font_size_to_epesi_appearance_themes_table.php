<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_appearance_themes', function (Blueprint $table): void {
            // Theme::FONT_SIZE_* — small, default (this app's own shipped
            // size) or large.
            $table->string('font_size')->default('default')->after('density');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_appearance_themes', function (Blueprint $table): void {
            $table->dropColumn('font_size');
        });
    }
};
