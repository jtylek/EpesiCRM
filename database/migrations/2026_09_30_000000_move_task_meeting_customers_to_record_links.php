<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves Tasks' and Meetings' customers (task_customer/meeting_customer,
 * Contact-only) and customerCompanies (task_customer_company/
 * meeting_customer_company, added by add_customer_companies_to_activities)
 * into the shared epesi_recordbrowser_links table, as one Customers field
 * (Field::customers(), a multi-pick Contact-or-Company typeahead) rather
 * than two separate pivots per recordset — see Task::customers()/
 * customerCompanies() (now MorphToMany over this same table) for how
 * existing callers keep working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['pivot' => 'task_customer', 'source_type' => 'task', 'source_key' => 'task_id', 'target_type' => 'contact', 'target_key' => 'contact_id'],
            ['pivot' => 'task_customer_company', 'source_type' => 'task', 'source_key' => 'task_id', 'target_type' => 'company', 'target_key' => 'company_id'],
            ['pivot' => 'meeting_customer', 'source_type' => 'meeting', 'source_key' => 'meeting_id', 'target_type' => 'contact', 'target_key' => 'contact_id'],
            ['pivot' => 'meeting_customer_company', 'source_type' => 'meeting', 'source_key' => 'meeting_id', 'target_type' => 'company', 'target_key' => 'company_id'],
        ] as $pivot) {
            DB::table($pivot['pivot'])->orderBy($pivot['source_key'])->orderBy($pivot['target_key'])->get()
                ->each(function (object $row) use ($pivot, $now): void {
                    DB::table('epesi_recordbrowser_links')->insert([
                        'source_type' => $pivot['source_type'],
                        'source_id' => $row->{$pivot['source_key']},
                        'field' => 'customers',
                        'target_type' => $pivot['target_type'],
                        'target_id' => $row->{$pivot['target_key']},
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
        }

        Schema::dropIfExists('task_customer');
        Schema::dropIfExists('task_customer_company');
        Schema::dropIfExists('meeting_customer');
        Schema::dropIfExists('meeting_customer_company');
    }

    public function down(): void
    {
        Schema::create('task_customer', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->primary(['task_id', 'contact_id']);
        });

        Schema::create('task_customer_company', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->primary(['task_id', 'company_id']);
        });

        Schema::create('meeting_customer', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->primary(['meeting_id', 'contact_id']);
        });

        Schema::create('meeting_customer_company', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->primary(['meeting_id', 'company_id']);
        });

        foreach ([
            ['pivot' => 'task_customer', 'source_type' => 'task', 'source_key' => 'task_id', 'target_type' => 'contact', 'target_key' => 'contact_id'],
            ['pivot' => 'task_customer_company', 'source_type' => 'task', 'source_key' => 'task_id', 'target_type' => 'company', 'target_key' => 'company_id'],
            ['pivot' => 'meeting_customer', 'source_type' => 'meeting', 'source_key' => 'meeting_id', 'target_type' => 'contact', 'target_key' => 'contact_id'],
            ['pivot' => 'meeting_customer_company', 'source_type' => 'meeting', 'source_key' => 'meeting_id', 'target_type' => 'company', 'target_key' => 'company_id'],
        ] as $pivot) {
            DB::table('epesi_recordbrowser_links')
                ->where('field', 'customers')
                ->where('source_type', $pivot['source_type'])
                ->where('target_type', $pivot['target_type'])
                ->orderBy('id')->get()
                ->each(function (object $row) use ($pivot): void {
                    DB::table($pivot['pivot'])->insert([
                        $pivot['source_key'] => $row->source_id,
                        $pivot['target_key'] => $row->target_id,
                    ]);
                });
        }

        DB::table('epesi_recordbrowser_links')->where('field', 'customers')
            ->whereIn('source_type', ['task', 'meeting'])->delete();
    }
};
