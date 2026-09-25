<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_applets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('widget');
            $table->unsignedTinyInteger('col');
            $table->unsignedSmallInteger('pos');
            $table->unique(['user_id', 'widget']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_applets');
    }
};
