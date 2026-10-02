<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only an administrator's explicit choice is a row; what a module
        // enables by default is code (RecordsetFeatures::define()), so a
        // recordset needs no seeding when its module is installed.
        Schema::create('epesi_recordbrowser_recordset_features', function (Blueprint $table) {
            $table->id();
            $table->string('model_type', 100);
            $table->string('feature', 50);
            $table->boolean('enabled');
            $table->timestamps();
            $table->unique(['model_type', 'feature'], 'rb_recordset_feature_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_recordbrowser_recordset_features');
    }
};
