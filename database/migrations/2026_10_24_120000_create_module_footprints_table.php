<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a module's migrations created: the tables, and the rows they seeded
 * into shared tables such as `common_data`. ModuleFootprint records it while
 * the migrations run; uninstalling with data removes exactly that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_footprints', function (Blueprint $table): void {
            $table->id();
            $table->string('module_id', 128)->index();
            $table->string('kind', 16);
            $table->string('table_name', 64);
            $table->unsignedBigInteger('row_id')->nullable();
            $table->string('migration', 255)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_footprints');
    }
};
