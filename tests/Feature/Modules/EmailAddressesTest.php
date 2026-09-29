<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\CreateCompany;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\EditCompany;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\CreateContact;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\EditContact;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ListContacts;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * E-mail addresses as a collection — step 7 of the plan in
 * AI-shared/Epesi-custom-fields.md: what replaced a contact's and a
 * company's `email` column and Mail's separate "E-mail addresses" table.
 */
class EmailAddressesTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('main'));
    }

    public function test_the_form_saves_several_addresses_and_the_list_shows_the_first(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateContact::class)
            ->fillForm([
                'last_name' => 'Anders',
                'first_name' => 'Ann',
                'emails' => [
                    ['kind' => 'work', 'value' => 'ann@work.test'],
                    ['kind' => 'private', 'value' => 'ann@home.test'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $ann = Contact::query()->where('last_name', 'Anders')->sole();
        $this->assertSame(['work', 'private'], $ann->emails->pluck('kind')->all());
        $this->assertSame(['ann@work.test', 'ann@home.test'], $ann->emails->pluck('value')->all());

        Livewire::test(ListContacts::class)->assertSee('ann@work.test');
    }

    public function test_a_contact_needs_no_email_address_at_all(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $ann = Contact::create(['last_name' => 'Anders', 'first_name' => 'Ann']);

        $this->assertCount(0, $ann->emails);
    }

    public function test_an_address_already_on_another_contact_is_refused_and_names_it(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();

        $ann = Contact::create(['last_name' => 'Anders', 'first_name' => 'Ann']);
        $ann->syncCollection('emails', [['kind' => 'work', 'value' => 'ann@work.test']]);

        Livewire::test(CreateContact::class)
            ->fillForm(['last_name' => 'Brown', 'first_name' => 'Bob', 'emails' => [['kind' => 'work', 'value' => 'Ann@Work.test']]])
            ->call('create')
            ->assertHasFormErrors(['emails.0.value']);

        $undoRepeaterFake();

        $this->assertSame(0, Contact::query()->where('last_name', 'Brown')->count());
    }

    public function test_an_address_already_on_another_company_is_refused_and_names_it(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();

        $acme = Company::create(['company_name' => 'Acme']);
        $acme->syncCollection('emails', [['kind' => 'work', 'value' => 'sales@acme.test']]);

        Livewire::test(CreateCompany::class)
            ->fillForm(['company_name' => 'Acme Poland', 'emails' => [['kind' => 'work', 'value' => 'sales@acme.test']]])
            ->call('create')
            ->assertHasFormErrors(['emails.0.value']);

        $undoRepeaterFake();
    }

    public function test_editing_a_contact_may_keep_its_own_address_unchanged(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();

        $ann = Contact::create(['last_name' => 'Anders', 'first_name' => 'Ann']);
        $ann->syncCollection('emails', [['kind' => 'work', 'value' => 'ann@work.test']]);

        Livewire::test(EditContact::class, ['record' => $ann->getRouteKey()])
            ->fillForm(['emails' => [['id' => $ann->emails->sole()->id, 'kind' => 'private', 'value' => 'ann@work.test']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $this->assertSame('private', $ann->refresh()->emails->sole()->kind);
    }

    public function test_editing_a_company_may_keep_its_own_address_but_not_take_anothers(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();

        $acme = Company::create(['company_name' => 'Acme']);
        $acme->syncCollection('emails', [['kind' => 'work', 'value' => 'sales@acme.test']]);
        $wayne = Company::create(['company_name' => 'Wayne Enterprises']);
        $wayne->syncCollection('emails', [['kind' => 'work', 'value' => 'contact@wayne.test']]);

        Livewire::test(EditCompany::class, ['record' => $wayne->getRouteKey()])
            ->fillForm(['emails' => [['id' => $wayne->emails->sole()->id, 'kind' => 'work', 'value' => 'sales@acme.test']]])
            ->call('save')
            ->assertHasFormErrors(['emails.0.value']);

        Livewire::test(EditCompany::class, ['record' => $wayne->getRouteKey()])
            ->fillForm(['emails' => [['id' => $wayne->emails->sole()->id, 'kind' => 'work', 'value' => 'contact@wayne.test']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();
    }
}
