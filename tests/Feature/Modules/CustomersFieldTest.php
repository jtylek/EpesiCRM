<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\Pages\CreatePhoneCall;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\CreateTask;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\EditTask;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Task's Customers field (Field::customers(), a multi-pick Contact-or-Company
 * typeahead) replaced two separate multiselects — see Task::customers()/
 * customerCompanies(), the MorphToMany relations that still read/write the
 * same underlying link rows for every non-form caller.
 */
class CustomersFieldTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_the_form_picks_a_mix_of_contacts_and_companies(): void
    {
        $user = $this->userWithRole('employee');
        $home = Company::create(['company_name' => 'Home']);
        Contact::create(['last_name' => 'Me', 'first_name' => 'Ann', 'company_id' => $home->id, 'user_id' => $user->id]);
        $this->actingAs($user);
        $employee = Contact::create(['last_name' => 'Worker', 'first_name' => 'Wendy', 'company_id' => $home->id]);
        $contact = Contact::create(['last_name' => 'Buyer', 'first_name' => 'Ann']);
        $company = Company::create(['company_name' => 'Acme']);

        Livewire::test(CreateTask::class)
            ->fillForm([
                'title' => 'Send offer',
                'employees' => [$employee->id],
                'customers' => ['contact:'.$contact->id, 'company:'.$company->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::sole();
        $this->assertSame([$contact->id], $task->customers()->pluck('contacts.id')->all());
        $this->assertSame([$company->id], $task->customerCompanies()->pluck('companies.id')->all());
    }

    public function test_editing_round_trips_the_picked_tokens(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Buyer', 'first_name' => 'Ann']);
        $company = Company::create(['company_name' => 'Acme']);
        $task = Task::create(['title' => 'Send offer']);
        $task->customers()->attach($contact);
        $task->customerCompanies()->attach($company);

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->assertFormSet(['customers' => ['contact:'.$contact->id, 'company:'.$company->id]]);
    }

    public function test_customer_search_matches_words_across_contact_name_fields(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);
        $contact = Contact::create(['first_name' => 'Joe', 'last_name' => 'Marcozzi']);
        $company = Company::create(['company_name' => 'Joe Market']);

        $page = Livewire::test(CreateTask::class);
        $select = $page->instance()->form->getComponent(
            fn ($component): bool => $component instanceof Select && $component->getName() === 'customers',
        );

        $this->assertTrue(collect($select->getSearchResults('joe'))->collapse()->has('contact:'.$contact->id));
        $this->assertTrue(collect($select->getSearchResults('joe m'))->collapse()->has('contact:'.$contact->id));
        $this->assertTrue(collect($select->getSearchResults('joe mar'))->collapse()->has('company:'.$company->id));
    }

    public function test_customer_options_include_the_type_icon_for_contacts_and_companies(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['first_name' => 'Joe', 'last_name' => 'Marcozzi']);
        $company = Company::create(['company_name' => 'Joe Market']);

        foreach ([
            [Livewire::test(CreatePhoneCall::class), 'customer'],
            [Livewire::test(CreateTask::class), 'customers'],
        ] as [$page, $fieldName]) {
            $select = $page->instance()->form->getComponent(
                fn ($component): bool => $component instanceof Select && $component->getName() === $fieldName,
            );
            /** @var array<string, string> $options */
            $options = collect($select->getSearchResults('joe'))->collapse()->all();

            foreach (['contact:'.$contact->id, 'company:'.$company->id] as $token) {
                $this->assertStringContainsString('<svg', $options[$token]);
                $this->assertStringContainsString('display: inline-block; vertical-align: middle;', $options[$token]);
            }
        }
    }
}
