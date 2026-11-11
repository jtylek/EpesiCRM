<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ViewCompany;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\MeetingResource;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\PhoneCallResource;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\CreateTask;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\Filament\RelationManagers\LinkedRecordsRelationManager;
use Epesi\Modules\RecordBrowser\Recordset\IncomingLinks;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Premium\Accounting\Filament\Resources\Expenses\ExpenseResource;
use Premium\Accounting\Filament\Resources\Invoices\InvoiceResource;
use Premium\DomainsRegister\Filament\Resources\Domains\DomainResource;
use Premium\ProjectsTickets\Filament\Resources\Projects\ProjectResource;
use Premium\ProjectsTickets\Filament\Resources\Tickets\TicketResource;
use Premium\SalesOpportunity\Filament\Resources\SalesOpportunities\SalesOpportunityResource;
use Premium\Timesheets\Filament\Resources\Rates\RateResource;
use Premium\Timesheets\Filament\Resources\Timesheets\TimesheetResource;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class IncomingLinksTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_tasks_are_found_through_customers_employees_and_related_with_visibility_preserved(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Customer', 'first_name' => 'Ann']);
        $customer = Task::create(['title' => 'Customer task']);
        $customer->customers()->attach($contact);
        $employee = Task::create(['title' => 'Employee task']);
        $employee->employees()->attach($contact);
        $related = Task::create(['title' => 'Related task']);
        $related->syncRecordLinks('linked_to', ['contact:'.$contact->id]);
        $private = Task::create(['title' => 'Private task', 'permission' => RecordPermission::Private]);
        $private->syncRecordLinks('linked_to', ['contact:'.$contact->id]);

        $this->actingAs($this->userWithRole('employee'));
        $fields = IncomingLinks::for($contact)[TaskResource::class];
        $this->assertEqualsCanonicalizing([$customer->id, $employee->id, $related->id], IncomingLinks::query(TaskResource::class, $contact, $fields)->pluck('id')->all());

        Livewire::test(LinkedRecordsRelationManager::class, [
            'ownerRecord' => $contact,
            'pageClass' => ViewContact::class,
            'sourceResource' => TaskResource::class,
        ])->assertCanSeeTableRecords([$customer, $employee, $related])
            ->assertCanNotSeeTableRecords([$private]);
    }

    public function test_the_tab_has_no_filters(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Customer', 'first_name' => 'Ann']);

        $manager = Livewire::test(LinkedRecordsRelationManager::class, [
            'ownerRecord' => $contact,
            'pageClass' => ViewContact::class,
            'sourceResource' => TaskResource::class,
        ]);

        $this->assertSame([], $manager->instance()->getTable()->getFilters());
    }

    public function test_a_companys_phone_calls_tab_has_no_customer_column_or_filter(): void
    {
        $this->actingAs($this->userWithRole('manager'));
        $company = Company::create(['company_name' => 'Acme']);
        $other = Company::create(['company_name' => 'Beta']);
        $call = PhoneCall::create(['subject' => 'Offer', 'called_at' => '2026-09-27 10:00:00', 'customer_type' => 'company', 'customer_id' => $company->id]);
        PhoneCall::create(['subject' => 'Other offer', 'called_at' => '2026-09-27 11:00:00', 'customer_type' => 'company', 'customer_id' => $other->id]);

        // Company also picks up the Linked to field, which offers
        // Companies by default — customer is the Customer field whose own
        // column and filter should be hidden.
        $fields = IncomingLinks::for($company)[PhoneCallResource::class];
        $this->assertSame(['customer', 'linked_to'], collect($fields)->map->name->all());

        $manager = Livewire::test(LinkedRecordsRelationManager::class, [
            'ownerRecord' => $company,
            'pageClass' => ViewCompany::class,
            'sourceResource' => PhoneCallResource::class,
        ]);
        $manager->assertCanSeeTableRecords([$call])
            ->assertTableColumnDoesNotExist('customer');

        $this->assertArrayNotHasKey('customer', $manager->instance()->getTable()->getFilters());
    }

    public function test_edit_shows_only_to_a_user_who_may_edit_the_record_and_no_row_offers_delete(): void
    {
        $author = $this->userWithRole('employee');
        $other = $this->userWithRole('employee');

        $this->actingAs($author);
        $contact = Contact::create(['last_name' => 'Customer', 'first_name' => 'Ann']);
        $task = Task::create(['title' => 'Renew contract', 'permission' => RecordPermission::PublicReadOnly]);
        $task->customers()->attach($contact);

        $this->actingAs($other);
        $manager = Livewire::test(LinkedRecordsRelationManager::class, [
            'ownerRecord' => $contact,
            'pageClass' => ViewContact::class,
            'sourceResource' => TaskResource::class,
        ]);
        $manager->assertTableActionHidden('edit', $task);
        $manager->assertTableActionDoesNotExist('delete');

        $this->actingAs($author);
        Livewire::test(LinkedRecordsRelationManager::class, [
            'ownerRecord' => $contact,
            'pageClass' => ViewContact::class,
            'sourceResource' => TaskResource::class,
        ])->assertTableActionVisible('edit', $task);
    }

    public function test_new_fills_the_link_in(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Customer', 'first_name' => 'Ann']);

        Livewire::withQueryParams(['link' => 'contact:'.$contact->id])
            ->test(CreateTask::class)
            ->assertFormSet(['customers' => ['contact:'.$contact->id]]);
    }

    public function test_a_contacts_tabs_list_only_the_fields_that_mean_this_person_is_involved(): void
    {
        $this->actingAs($this->userWithRole('manager'));
        $contact = Contact::create(['last_name' => 'Customer', 'first_name' => 'Ann']);
        $links = IncomingLinks::for($contact);

        $names = fn (string $resource): ?array => isset($links[$resource]) ? collect($links[$resource])->map->name->sort()->values()->all() : null;

        $this->assertSame(['customers', 'employees', 'linked_to'], $names(MeetingResource::class));

        $expected = [
            ExpenseResource::class => null,
            InvoiceResource::class => ['customer'],
            DomainResource::class => ['client'],
            ProjectResource::class => ['customers'],
            TicketResource::class => ['assignees', 'ticket_owner_id'],
            SalesOpportunityResource::class => ['customers'],
            RateResource::class => null,
            TimesheetResource::class => ['customer', 'employee_id'],
        ];

        foreach ($expected as $resource => $fields) {
            if (! class_exists($resource) || ! in_array($resource, Filament::getPanel('main')->getResources(), true)) {
                continue;
            }

            $this->assertSame($fields, $names($resource), $resource);
        }
    }
}
