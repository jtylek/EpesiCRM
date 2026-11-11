<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The `tax_rates` table. Tax rates used to belong to the Accounting module;
 * on an installation that has them there, this takes that table over instead
 * of creating an empty one:
 *
 * - renames it, so every id stays and the foreign keys of Accounting's invoice,
 *   expense and reusable lines follow it (InnoDB and SQLite both repoint them);
 * - rewrites the morph alias stored in every `*_type` column, and the activity
 *   log name, which is the same string, to `tax_rate`;
 * - forgets Accounting's footprint of the table, so uninstalling Accounting
 *   leaves it alone.
 *
 * Both of Accounting's names are handled: `premium_accounting_tax_rates` (after
 * its 2026_10_06_110000 rename) and `epesi_accounting_tax_rates` (before it,
 * when an update registers this module ahead of Accounting's own migrations).
 *
 * Dated before Accounting's create migration, which references this table, so
 * a single `migrate` over every module (a new installation, the tests) runs it
 * first; Accounting also lists epesi/taxrates in `requires`. Each step touches
 * only what still has an old name, so a run that stopped half way (MySQL
 * doesn't roll back DDL) can be run again. Self-contained: it runs before this
 * module's classes are autoloadable.
 */
return new class extends Migration
{
    /** Accounting's names for the table, newest first. */
    private const OLD_TABLES = ['premium_accounting_tax_rates', 'epesi_accounting_tax_rates'];

    /** Accounting's morph aliases (also its activity log names) for a tax rate. */
    private const OLD_ALIASES = ['premium_accounting_tax_rate', 'accounting_tax_rate'];

    private const ALIAS = 'tax_rate';

    public function up(): void
    {
        if (! Schema::hasTable('tax_rates')) {
            $old = collect(self::OLD_TABLES)->first(fn (string $table): bool => Schema::hasTable($table));

            $old !== null ? Schema::rename($old, 'tax_rates') : $this->create();
        }

        $this->renameAliases();
        $this->forgetAccountingFootprints();
    }

    /** Nothing to undo: the table may hold the rates Accounting's documents point at. */
    public function down(): void {}

    private function create(): void
    {
        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            // The index names are the ones the table had in Accounting, so an
            // installation that moved it here has the same schema as a new one.
            $table->unsignedBigInteger('legacy_id')->nullable()->unique('epesi_accounting_tax_rates_legacy_id_unique');
            $table->string('name', 64);
            $table->string('code', 32)->nullable();
            $table->string('description', 255)->nullable();
            $table->decimal('rate', 8, 4)->default(0);
            $table->string('tax_type', 32)->default('standard');
            $table->string('country_code', 2)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'epesi_accounting_tax_rates_created_by_foreign')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();

        // The usual European set; rated taxes differ by country, and an
        // administrator adds or changes them. The 0% codes are kept apart
        // because tax returns report them apart: zero rated (exports,
        // intra-EU supplies), exempt, and reverse charge (the customer
        // accounts for the VAT).
        foreach ([
            ['name' => 'VAT 23%', 'code' => 'VAT23', 'rate' => 23, 'tax_type' => 'standard'],
            ['name' => 'VAT 8%', 'code' => 'VAT8', 'rate' => 8, 'tax_type' => 'reduced'],
            ['name' => 'VAT 5%', 'code' => 'VAT5', 'rate' => 5, 'tax_type' => 'reduced'],
            ['name' => '0% (no tax)', 'code' => 'ZERO', 'rate' => 0, 'tax_type' => 'zero_rated'],
            ['name' => 'Exempt', 'code' => 'EXEMPT', 'rate' => 0, 'tax_type' => 'exempt'],
            ['name' => 'Reverse charge', 'code' => 'REVERSE', 'rate' => 0, 'tax_type' => 'reverse_charge'],
        ] as $tax) {
            DB::table('tax_rates')->updateOrInsert(['code' => $tax['code']], [...$tax, 'active' => true, 'updated_at' => $now, 'created_at' => $now]);
        }
    }

    /**
     * Every `*_type` column of every table: that is how polymorphic columns are
     * named here (activity_log, attachment and mail links, record links,
     * favourites, recent, browse modes, watchdog, reminders, custom fields,
     * field overrides, recordset features, other modules' tables).
     */
    private function renameAliases(): void
    {
        $columns = Schema::hasTable('activity_log') ? ['activity_log' => ['log_name']] : [];

        foreach (Schema::getTables(Schema::getCurrentSchemaName()) as $table) {
            foreach (Schema::getColumns($table['name']) as $column) {
                if (str_ends_with($column['name'], '_type') && preg_match('/char|text|string|clob/i', $column['type'])) {
                    $columns[$table['name']][] = $column['name'];
                }
            }
        }

        foreach ($columns as $table => $names) {
            foreach (array_unique($names) as $column) {
                DB::table($table)->whereIn($column, self::OLD_ALIASES)->update([$column => self::ALIAS]);
            }
        }
    }

    private function forgetAccountingFootprints(): void
    {
        if (Schema::hasTable('module_footprints')) {
            DB::table('module_footprints')->where('kind', 'table')->whereIn('table_name', self::OLD_TABLES)->delete();
        }
    }
};
