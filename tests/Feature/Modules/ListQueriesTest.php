<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ListCompanies;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\Pages\ListPhoneCalls;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ListTasks;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * A list page loads what its columns show for the whole page at once, not
 * once per row (AI-shared/Epesi-optimization.md): the owner behind an e-mail
 * link, the records of a Relation(s) column, a Customer.
 */
class ListQueriesTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('main'));
        $this->actingAs($this->userWithRole('employee'));
    }

    public function test_e_mail_links_do_not_load_their_owner_again(): void
    {
        foreach (range(1, 5) as $i) {
            Company::create(['company_name' => "Company {$i}"])
                ->syncCollection('emails', [['kind' => 'work', 'value' => "office{$i}@example.test"]]);
        }

        $queries = $this->queriesDuring(fn () => Livewire::test(ListCompanies::class)->assertSee('office5@example.test'));

        $this->assertSame([], $this->matching($queries, '/from "companies" where "companies"\."id" = \?/'));
    }

    public function test_a_relations_column_loads_the_whole_page_in_one_query(): void
    {
        foreach (range(1, 5) as $i) {
            $employee = Contact::create(['last_name' => "Employee {$i}", 'first_name' => 'Eve']);
            Task::create(['title' => "Task {$i}"])->employees()->attach($employee);
        }

        $queries = $this->queriesDuring(fn () => Livewire::test(ListTasks::class)->assertSee('Employee 5'));

        // The visibility scope joins task_employee too; a per-row load asks for one task.
        $this->assertSame([], $this->matching($queries, '/"task_employee"\."task_id" = \?/'));
        $this->assertCount(1, $this->matching($queries, '/"task_employee"\."task_id" in \(/'));
    }

    public function test_a_customer_column_loads_the_whole_page_at_once(): void
    {
        foreach (range(1, 5) as $i) {
            $call = new PhoneCall(['subject' => "Call {$i}", 'called_at' => now()]);
            $call->customer()->associate(Contact::create(['last_name' => "Customer {$i}", 'first_name' => 'Cy']));
            $call->save();
        }

        $queries = $this->queriesDuring(fn () => Livewire::test(ListPhoneCalls::class)->assertSee('Customer 5'));

        $this->assertSame([], $this->matching($queries, '/from "contacts" where "contacts"\."id" = \?/'));
    }

    /** @return list<string> */
    protected function queriesDuring(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        DB::disableQueryLog();

        return array_column(DB::getQueryLog(), 'query');
    }

    /** @return list<string> */
    protected function matching(array $queries, string $pattern): array
    {
        return array_values(preg_grep($pattern, $queries));
    }
}
