<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Each CRM list opens sorted by one column; without an index on it (behind
 * `deleted_at`, which every model's soft deletes put first) the whole table is
 * sorted on every page, search and page change.
 */
class ListSortIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_crm_list_has_an_index_on_its_default_sort_column(): void
    {
        foreach ([
            'companies' => ['company_name'],
            'contacts' => ['last_name', 'first_name'],
            'tasks' => ['deadline'],
            'meetings' => ['date'],
            'phone_calls' => ['called_at'],
        ] as $table => $columns) {
            $this->assertTrue(
                collect(Schema::getIndexes($table))->contains(fn (array $index): bool => $index['columns'] === ['deleted_at', ...$columns]),
                "`{$table}` has no index on (deleted_at, ".implode(', ', $columns).').',
            );
        }
    }

    public function test_recordset_check_stays_quiet_about_the_indexed_crm_lists(): void
    {
        // Only the CRM lists: an installation's other modules (Premium ones, say) may
        // ship a recordset whose default sort column has no index, and that is
        // exactly what the hint is for.
        $this->artisan('recordset:check')
            ->doesntExpectOutputToContain('CompanyResource default sort')
            ->doesntExpectOutputToContain('ContactResource default sort')
            ->doesntExpectOutputToContain('TaskResource default sort')
            ->doesntExpectOutputToContain('MeetingResource default sort')
            ->doesntExpectOutputToContain('PhoneCallResource default sort')
            ->assertSuccessful();
    }

    public function test_recordset_check_hints_when_the_default_sort_column_has_no_index(): void
    {
        Schema::table('companies', fn (Blueprint $table) => $table->dropIndex('companies_list_sort_index'));

        // A hint, not a problem: the list works, it only gets slow with many rows.
        $this->artisan('recordset:check')
            ->expectsOutputToContain('no index starts with `companies`.`company_name`')
            ->assertSuccessful();
    }
}
