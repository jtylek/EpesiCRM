<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The rate provider defaults to Automatic: NBP when the home currency is PLN,
 * ECB otherwise. Settings still holding the old ECB default take it too; ECB
 * was the only default there was, with no choice made yet to keep.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_currency_settings', function (Blueprint $table): void {
            $table->string('rate_provider', 16)->default('auto')->change();
        });

        DB::table('epesi_currency_settings')->where('rate_provider', 'ecb')->update(['rate_provider' => 'auto']);
    }

    public function down(): void
    {
        DB::table('epesi_currency_settings')->where('rate_provider', 'auto')->update(['rate_provider' => 'ecb']);

        Schema::table('epesi_currency_settings', function (Blueprint $table): void {
            $table->string('rate_provider', 16)->default('ecb')->change();
        });
    }
};
