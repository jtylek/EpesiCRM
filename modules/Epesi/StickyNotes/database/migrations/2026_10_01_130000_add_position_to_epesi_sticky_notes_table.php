<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The order a user drags their notes into; the newest note goes first until moved. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_sticky_notes', function (Blueprint $table) {
            $table->integer('position')->default(0)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_sticky_notes', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
