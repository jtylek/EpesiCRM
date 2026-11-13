<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\CreateCompany;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\EditCompany;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\CreateContact;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\EditContact;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ListContacts;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\EmailAddress;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * E-mail addresses as a collection — step 7 of the plan in
 * AI-shared/recordbrowser/collections.md: what replaced a contact's and a
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

    public function test_a_deleted_contacts_address_can_be_given_to_another_contact(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();

        $duplicate = Contact::create(['last_name' => 'Anders', 'first_name' => 'Ann']);
        $duplicate->syncCollection('emails', [['kind' => 'work', 'value' => 'ann@work.test']]);
        $duplicate->delete();

        $ann = Contact::create(['last_name' => 'Anders', 'first_name' => 'Anna']);

        Livewire::test(EditContact::class, ['record' => $ann->getRouteKey()])
            ->fillForm(['emails' => [['kind' => 'work', 'value' => 'Ann@Work.test']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $this->assertSame(['ann@work.test'], $ann->fresh()->emails->pluck('value')->all());
        // Still the deleted record's, for Restore.
        $this->assertSame(['ann@work.test'], Contact::withTrashed()->find($duplicate->id)->emails->pluck('value')->all());
    }

    public function test_restore_takes_a_free_address_back_and_holds_it_again(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $ann = Contact::create(['last_name' => 'Anders', 'first_name' => 'Ann']);
        $ann->syncCollection('emails', [['kind' => 'work', 'value' => 'ann@work.test']]);

        $ann->delete();
        $this->assertNull(EmailAddress::query()->where('value', 'ann@work.test')->sole()->live_value);

        $ann->restore();
        $this->assertSame('ann@work.test', EmailAddress::query()->where('value', 'ann@work.test')->sole()->live_value);

        $bob = Contact::create(['last_name' => 'Brown', 'first_name' => 'Bob']);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Anders');
        $bob->syncCollection('emails', [['kind' => 'work', 'value' => 'ann@work.test']]);
    }

    public function test_a_save_sent_twice_keeps_one_row_for_the_new_address(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $maria = Contact::create(['last_name' => 'Dahl', 'first_name' => 'Maria']);
        $maria->syncCollection('emails', [['kind' => 'work', 'value' => 'maria.dahl@x.test']]);
        $firstId = $maria->emails()->sole()->id;

        // The form's state as it was posted: the saved address, plus a new one with no id.
        $posted = [
            ['id' => $firstId, 'kind' => 'work', 'value' => 'maria.dahl@x.test'],
            ['kind' => 'work', 'value' => 'Maria@x.test'],
        ];

        $maria->syncCollection('emails', $posted);
        $maria->syncCollection('emails', $posted);

        $this->assertSame(['maria.dahl@x.test', 'maria@x.test'], $maria->emails()->orderBy('position')->pluck('value')->all());
    }

    public function test_restore_leaves_an_address_taken_meanwhile_and_history_shows_it_gone(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $ann = Contact::create(['last_name' => 'Anders', 'first_name' => 'Ann']);
        $ann->syncCollection('emails', [['kind' => 'work', 'value' => 'shared@work.test'], ['kind' => 'private', 'value' => 'ann@home.test']]);
        $ann->delete();

        $bob = Contact::create(['last_name' => 'Brown', 'first_name' => 'Bob']);
        $bob->syncCollection('emails', [['kind' => 'work', 'value' => 'shared@work.test']]);

        $ann->restore();

        $this->assertSame(['ann@home.test'], $ann->fresh()->emails->pluck('value')->all());
        $this->assertSame(['shared@work.test'], $bob->fresh()->emails->pluck('value')->all());

        $logged = $ann->activities()->get()->map(fn ($entry) => $entry->properties['old']['emails'] ?? null)->filter()->last();
        $this->assertStringContainsString('shared@work.test', implode(' ', $logged));
    }

    public function test_a_deleted_contact_keeps_holding_its_login_address(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $user = User::factory()->create(['email' => 'joe@acme.test']);
        $joe = Contact::create(['last_name' => 'Doe', 'first_name' => 'Joe', 'user_id' => $user->id]);
        $joe->syncCollection('emails', [['kind' => 'work', 'value' => 'joe@acme.test'], ['kind' => 'private', 'value' => 'joe@home.test']]);

        $joe->delete();

        $this->assertSame('joe@acme.test', EmailAddress::query()->where('value', 'joe@acme.test')->sole()->live_value);
        $this->assertNull(EmailAddress::query()->where('value', 'joe@home.test')->sole()->live_value);
    }

    public function test_the_migration_releases_only_what_deleted_records_hold(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $ann = Contact::create(['last_name' => 'Anders', 'first_name' => 'Ann']);
        $ann->syncCollection('emails', [['kind' => 'work', 'value' => 'ann@work.test']]);
        $gone = Contact::create(['last_name' => 'Gone', 'first_name' => 'Gary']);
        $gone->syncCollection('emails', [['kind' => 'work', 'value' => 'gary@work.test']]);
        $gone->delete();

        $migration = require base_path('modules/Epesi/RecordBrowser/database/migrations/2026_11_07_110000_make_email_addresses_unique_among_live_records.php');
        $migration->down();
        $migration->up();

        $this->assertSame('ann@work.test', EmailAddress::query()->where('value', 'ann@work.test')->sole()->live_value);
        $this->assertNull(EmailAddress::query()->where('value', 'gary@work.test')->sole()->live_value);
    }
}
