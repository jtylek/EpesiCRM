<?php

namespace Tests\Feature;

use App\Filament\Administration\Resources\Users\Pages\ListUsers;
use App\Filament\Administration\Resources\Users\Pages\ViewUser;
use App\Filament\Administration\Resources\Users\UserResource;
use App\Models\User;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Administration → Users: the person a login is for is its Contact, so that
 * is the column, not the login's own name.
 */
class UsersTableTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $admin;

    protected User $ann;

    protected User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->userWithRole('super_admin', ['name' => 'root']);
        $this->ann = $this->userFor('annk', 'Ann', 'Kowalska');
        $this->bob = $this->userFor('bobn', 'Bob', 'Nowak');

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('administration');
    }

    public function test_the_column_is_the_contacts_name(): void
    {
        Livewire::test(ListUsers::class)
            ->assertTableColumnExists('contact')
            ->assertTableColumnDoesNotExist('name')
            ->assertTableColumnStateSet('contact', 'Ann Kowalska', record: $this->ann)
            ->assertTableColumnStateSet('contact', 'Bob Nowak', record: $this->bob)
            // No contact: the account's own name, as displayName() says everywhere.
            ->assertTableColumnStateSet('contact', 'root', record: $this->admin);
    }

    public function test_users_are_found_by_their_contacts_name(): void
    {
        Livewire::test(ListUsers::class)
            ->searchTable('kowal')
            ->assertCanSeeTableRecords([$this->ann])
            ->assertCanNotSeeTableRecords([$this->bob, $this->admin])
            ->searchTable('ann kow')
            ->assertCanSeeTableRecords([$this->ann])
            ->assertCanNotSeeTableRecords([$this->bob])
            ->searchTable('bobn') // the login's own name still finds it
            ->assertCanSeeTableRecords([$this->bob])
            ->assertCanNotSeeTableRecords([$this->ann]);
    }

    public function test_users_sort_by_their_contacts_last_name(): void
    {
        Livewire::test(ListUsers::class)
            ->sortTable('contact')
            ->assertCanSeeTableRecords([$this->ann, $this->bob], inOrder: true)
            ->sortTable('contact', 'desc')
            ->assertCanSeeTableRecords([$this->bob, $this->ann], inOrder: true);
    }

    public function test_the_view_page_names_the_contact(): void
    {
        $this->assertSame('Ann Kowalska', UserResource::getRecordTitle($this->ann));

        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->assertSee('Ann Kowalska')
            ->assertSee('ann@example.test');
    }

    protected function userFor(string $login, string $firstName, string $lastName): User
    {
        $user = User::factory()->create(['name' => $login, 'email' => strtolower($firstName).'@example.test']);
        $user->assignRole('employee');
        $contact = Contact::create(['first_name' => $firstName, 'last_name' => $lastName, 'user_id' => $user->id]);
        $contact->syncCollection('emails', [['kind' => 'work', 'value' => $user->email]]);

        return $user;
    }
}
