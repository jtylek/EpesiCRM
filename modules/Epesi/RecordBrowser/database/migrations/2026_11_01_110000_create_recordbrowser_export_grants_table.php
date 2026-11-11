<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_recordbrowser_export_grants', function (Blueprint $table): void {
            $table->id();
            $table->string('model_type', 100);
            $table->string('subject_type', 10);
            $table->unsignedBigInteger('subject_id');
            $table->unique(['model_type', 'subject_type', 'subject_id'], 'rb_export_grants_unique');
            $table->index(['subject_type', 'subject_id'], 'rb_export_grants_subject');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_recordbrowser_export_grants');
    }
};
