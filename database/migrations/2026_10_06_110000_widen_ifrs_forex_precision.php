<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['ifrs_exchange_rates', 'ifrs_ledgers'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'rate')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->decimal('rate', 20, 10)->default(1)->change();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['ifrs_exchange_rates', 'ifrs_ledgers'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'rate')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->decimal('rate', 13, 4)->default(1)->change();
                });
            }
        }
    }
};
