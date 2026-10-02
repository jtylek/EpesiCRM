<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moves an already-installed phone_calls table from the old plain
     * contact_id/company_id FKs onto the customer_type/customer_id morphTo
     * pair Field::customer() now uses (see create_phone_calls_table's
     * docblock, edited to create that shape directly). A fresh install
     * never has contact_id/company_id in the first place — the column
     * check makes this a no-op there instead of colliding with the
     * consolidated base migration.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('phone_calls', 'contact_id')) {
            return;
        }

        Schema::table('phone_calls', function (Blueprint $table) {
            $table->nullableMorphs('customer');
        });

        DB::table('phone_calls')->whereNotNull('contact_id')->update([
            'customer_type' => 'contact',
            'customer_id' => DB::raw('contact_id'),
        ]);
        DB::table('phone_calls')->whereNotNull('company_id')->whereNull('contact_id')->update([
            'customer_type' => 'company',
            'customer_id' => DB::raw('company_id'),
        ]);

        Schema::table('phone_calls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
            $table->dropConstrainedForeignId('company_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('phone_calls', 'customer_type')) {
            return;
        }

        Schema::table('phone_calls', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::table('phone_calls')->where('customer_type', 'contact')->update([
            'contact_id' => DB::raw('customer_id'),
        ]);
        DB::table('phone_calls')->where('customer_type', 'company')->update([
            'company_id' => DB::raw('customer_id'),
        ]);

        Schema::table('phone_calls', function (Blueprint $table) {
            $table->dropMorphs('customer');
        });
    }
};
