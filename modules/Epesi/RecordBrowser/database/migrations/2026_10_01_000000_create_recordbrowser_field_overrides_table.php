<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_recordbrowser_field_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('model_type', 100);
            $table->string('field', 100);
            $table->json('properties');
            $table->timestamps();
            $table->unique(['model_type', 'field'], 'rb_field_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_recordbrowser_field_overrides');
    }
};
