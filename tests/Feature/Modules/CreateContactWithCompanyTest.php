<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\CreateContact;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\EditContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/** Legacy Epesi's "Create company" on a new contact: one step, address copied. */
class CreateContactWithCompanyTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('main'));
    }

    public function test_ticking_create_company_makes_the_company_with_the_contacts_address(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateContact::class)
            ->fillForm([
                'last_name' => 'Anders',
                'first_name' => 'Ann',
                'create_company' => true,
                'new_company_name' => 'Anders Ltd',
                'addresses' => [['kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $company = Company::query()->where('company_name', 'Anders Ltd')->firstOrFail();
        $contact = Contact::query()->where('last_name', 'Anders')->firstOrFail();

        $this->assertSame($company->getKey(), $contact->company_id);
        $this->assertSame('Warsaw', $company->addresses->first()->city);
        $this->assertSame('Warsaw', $contact->addresses->first()->city);
    }

    public function test_the_company_field_is_disabled_while_ticked(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        Livewire::test(CreateContact::class)
            ->assertFormFieldEnabled('company_id')
            ->fillForm(['create_company' => true])
            ->assertFormFieldDisabled('company_id');
    }

    public function test_the_company_name_is_required_once_ticked(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        Livewire::test(CreateContact::class)
            ->fillForm(['last_name' => 'Anders', 'first_name' => 'Ann', 'create_company' => true])
            ->call('create')
            ->assertHasFormErrors(['new_company_name']);
    }

    public function test_an_unticked_box_creates_no_company_and_edit_has_no_option(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        Livewire::test(CreateContact::class)
            ->fillForm(['last_name' => 'Anders', 'first_name' => 'Ann'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(0, Company::query()->count());

        Livewire::test(EditContact::class, ['record' => Contact::query()->firstOrFail()->getKey()])
            ->assertFormFieldIsHidden('new_company_name')
            ->assertFormFieldDoesNotExist('create_company');
    }
}
