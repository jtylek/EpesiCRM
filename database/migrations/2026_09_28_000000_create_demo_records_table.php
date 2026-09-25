<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which rows the demo data created (App\Support\DemoData), so they can be
 * removed again from Administration → Demo data without touching anything
 * the administrator added themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_records', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 64);
            $table->unsignedBigInteger('record_id');
            $table->unique(['table_name', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_records');
    }
};
