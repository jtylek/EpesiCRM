<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('module_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version');
            $table->string('path');
            $table->string('namespace');
            $table->string('provider_class')->nullable();
            $table->string('plugin_class')->nullable();
            $table->json('panels')->nullable();
            $table->json('manifest');
            $table->boolean('enabled')->default(true);
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
