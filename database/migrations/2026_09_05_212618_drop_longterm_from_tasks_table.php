<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jasiek: won't be used — drop it. Legacy `task.f_longterm` is ignored by
 * TasksImporter from this point on rather than imported into a dropped
 * column (see that class's docblock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('longterm');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('longterm')->default(false);
        });
    }
};
