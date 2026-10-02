<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('epesi_appearance_themes')
            ->where('name', 'Epesi Green')
            ->where('font_size', 'default')
            ->update(['font_size' => 'small']);
    }

    public function down(): void
    {
        DB::table('epesi_appearance_themes')
            ->where('name', 'Epesi Green')
            ->where('font_size', 'small')
            ->update(['font_size' => 'default']);
    }
};
