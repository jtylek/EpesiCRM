<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_background_worker_runs', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->boolean('successful')->default(false);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['successful', 'finished_at'], 'epesi_background_runs_success_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_background_worker_runs');
    }
};
