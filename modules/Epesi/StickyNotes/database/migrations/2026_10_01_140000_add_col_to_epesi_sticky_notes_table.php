<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The column of the notes board a note sits in, so notes can be stacked as well as put side by side. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_sticky_notes', function (Blueprint $table) {
            $table->unsignedTinyInteger('col')->default(0)->after('position');
        });

        // Notes made before the board had columns are spread over them.
        DB::table('epesi_sticky_notes')->update(['col' => DB::raw('id % 4')]);
    }

    public function down(): void
    {
        Schema::table('epesi_sticky_notes', function (Blueprint $table) {
            $table->dropColumn('col');
        });
    }
};
