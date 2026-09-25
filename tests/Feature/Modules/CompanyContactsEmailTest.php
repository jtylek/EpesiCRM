<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\EditCompany;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\RelationManagers\ContactsRelationManager;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class CompanyContactsEmailTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_contact_added_from_the_company_page_cannot_reuse_an_email(): void
    {
        $this->actingAs($this->userWithRole('manager'));
        $company = Company::create(['company_name' => 'Acme']);
        Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann', 'email' => 'ann@example.com']);

        $this->contacts($company)
            ->callAction(TestAction::make('create')->table(), data: ['first_name' => 'Bob', 'last_name' => 'Jones', 'email' => 'ann@example.com'])
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertSame(1, Contact::query()->count());

        $this->contacts($company)
            ->callAction(TestAction::make('create')->table(), data: ['first_name' => 'Bob', 'last_name' => 'Jones', 'email' => 'bob@example.com'])
            ->assertHasNoFormErrors();

        $this->assertSame(2, Contact::query()->count());
    }

    public function test_editing_a_contact_may_keep_its_own_email_but_not_take_anothers(): void
    {
        $this->actingAs($this->userWithRole('manager'));
        $company = Company::create(['company_name' => 'Acme']);
        Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann', 'email' => 'ann@example.com']);
        $bob = Contact::create(['last_name' => 'Jones', 'first_name' => 'Bob', 'email' => 'bob@example.com', 'company_id' => $company->id]);

        $edit = fn (string $email): Testable => $this->contacts($company)
            ->callAction(TestAction::make('edit')->table($bob), data: ['first_name' => 'Bob', 'last_name' => 'Jones', 'email' => $email]);

        $edit('ann@example.com')->assertHasFormErrors(['email' => 'unique']);
        $this->assertSame('bob@example.com', $bob->refresh()->email);

        $edit('bob@example.com')->assertHasNoFormErrors();
    }

    // Mounted as on an Edit page: Filament makes a relation manager read-only on
    // a View page, which hides the very actions under test.
    private function contacts(Company $company): Testable
    {
        return Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $company, 'pageClass' => EditCompany::class]);
    }
}
