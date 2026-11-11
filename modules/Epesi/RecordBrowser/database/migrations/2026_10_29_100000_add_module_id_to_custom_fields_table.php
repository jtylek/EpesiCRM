<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_fields', function (Blueprint $table): void {
            $table->string('module_id', 128)->nullable()->after('model_type');
            $table->index(['module_id', 'active'], 'custom_fields_module_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table): void {
            $table->dropIndex('custom_fields_module_active_index');
            $table->dropColumn('module_id');
        });
    }
};
