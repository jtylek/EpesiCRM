<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Referenced by ISO code everywhere, never by id: a code reads the same
        // on every install and in raw SQL.
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // The home (functional) currency is a dated fact: a company that moves
        // country changes it, and documents booked before keep the old one.
        Schema::create('currency_home_periods', function (Blueprint $table) {
            $table->id();
            $table->char('currency_code', 3);
            $table->date('effective_from')->unique();
            $table->timestamps();
        });

        // Only what a provider publishes (ECB: EUR -> X, NBP: X -> PLN, custom:
        // the pair an administrator typed). Cross rates are derived on read.
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 16);
            $table->char('base', 3);
            $table->char('quote', 3);
            $table->date('rate_date');
            // Unrounded: a fixed decimal count can't serve every pair (HUF -> EUR
            // is 0.0025...). Amounts round; rates don't.
            $table->double('rate');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'base', 'quote', 'rate_date'], 'currency_rates_pair_date_unique');
            $table->index(['provider', 'quote', 'rate_date'], 'currency_rates_quote_date_index');
        });

        Schema::create('epesi_currency_settings', function (Blueprint $table) {
            $table->id();
            $table->string('rate_provider', 16)->default('ecb');
            $table->boolean('auto_fetch')->default(true);
            $table->date('backfill_from')->nullable();
            $table->timestamp('last_fetch_at')->nullable();
            $table->text('last_fetch_result')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('currencies')->insert(array_map(fn (array $row, int $i) => [
            'code' => $row[0],
            'name' => $row[1],
            'decimals' => 2,
            'active' => true,
            'position' => $i,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            ['USD', 'US Dollar'],
            ['EUR', 'Euro'],
            ['GBP', 'British Pound'],
            ['PLN', 'Polish Zloty'],
        ], [0, 1, 2, 3]));

        DB::table('currency_home_periods')->insert([
            'currency_code' => 'USD',
            'effective_from' => '1970-01-01',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_currency_settings');
        Schema::dropIfExists('currency_rates');
        Schema::dropIfExists('currency_home_periods');
        Schema::dropIfExists('currencies');
    }
};
