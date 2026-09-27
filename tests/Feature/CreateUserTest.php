<?php

namespace Tests\Feature;

use App\Filament\Administration\Resources\Users\Pages\CreateUser;
use App\Filament\Administration\Resources\Users\Pages\EditUser;
use App\Models\User;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Administration → Users → Create: a login is made for a Contact. The
 * administrator chooses it instead of typing a name, and its e-mail address
 * comes along; a contact with no address can't become a user.
 */
class CreateUserTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');
    }

    public function test_the_form_asks_for_a_contact_not_a_name(): void
    {
        Livewire::test(CreateUser::class)
            ->assertFormFieldIsVisible('contact_id')
            ->assertFormFieldIsHidden('name');
    }

    public function test_choosing_a_contact_fills_in_its_email_address(): void
    {
        $ann = $this->contact('Ann', 'ann@example.test');

        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $ann->id])
            ->assertFormSet(['email' => 'ann@example.test']);
    }

    public function test_a_contact_with_no_email_address_is_refused_with_a_warning(): void
    {
        $bob = $this->contact('Bob', null);

        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $bob->id])
            ->assertNotified("Bob Tester can't be made a user")
            ->assertFormSet(['contact_id' => null, 'email' => null]);
    }

    public function test_a_contact_with_no_email_address_cannot_be_created_even_when_the_form_is_forced(): void
    {
        $bob = $this->contact('Bob', null);

        // As if the warning above had been bypassed.
        Livewire::test(CreateUser::class)
            ->set('data.contact_id', $bob->id)
            ->call('create')
            ->assertHasFormErrors(['contact_id']);

        $this->assertSame(1, User::query()->count(), 'only the administrator');
    }

    public function test_the_user_takes_the_contacts_name_and_email_and_is_linked_to_it(): void
    {
        $ann = $this->contact('Ann', 'ann@example.test');

        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $ann->id, 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password'])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'ann@example.test')->sole();
        $this->assertSame('Ann Tester', $user->name);
        $this->assertSame($user->id, $ann->refresh()->user_id);
        $this->assertTrue($ann->user->is($user));
        $this->assertSame('Ann Tester', $user->displayName());
    }

    public function test_the_email_is_the_contacts_whatever_the_form_posts(): void
    {
        $ann = $this->contact('Ann', 'ann@example.test');

        // The field is read-only in the form; a request can still post another.
        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $ann->id, 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password'])
            ->set('data.email', 'someone-else@example.test')
            ->call('create');

        $this->assertDatabaseHas('users', ['email' => 'ann@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'someone-else@example.test']);
    }

    public function test_a_contact_that_already_has_a_login_cannot_be_chosen_again(): void
    {
        $taken = User::factory()->create(['email' => 'cy@example.test']);
        $cy = Contact::create(['first_name' => 'Cy', 'last_name' => 'Tester', 'email' => 'cy@example.test', 'user_id' => $taken->id]);

        Livewire::test(CreateUser::class)
            ->set('data.contact_id', $cy->id)
            ->call('create')
            ->assertHasFormErrors(['contact_id']);

        $this->assertSame($taken->id, $cy->refresh()->user_id);
    }

    public function test_an_email_address_another_login_already_has_is_refused(): void
    {
        User::factory()->create(['email' => 'ann@example.test']);
        $ann = $this->contact('Ann', 'ann@example.test');

        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $ann->id])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertNull($ann->refresh()->user_id);
    }

    public function test_the_search_offers_contacts_with_no_login_yet(): void
    {
        $this->contact('Ann', 'ann@example.test');
        $this->contact('Bob', null);
        $cy = User::factory()->create();
        Contact::create(['first_name' => 'Cy', 'last_name' => 'Tester', 'email' => 'cy@example.test', 'user_id' => $cy->id]);

        Livewire::test(CreateUser::class)
            ->assertFormFieldExists('contact_id', function (Select $field): bool {
                $found = collect($field->getSearchResults('tester'));

                $this->assertCount(2, $found, 'Cy already has a login');
                $this->assertTrue($found->contains('Ann Tester · ann@example.test'));
                $this->assertTrue($found->contains('Bob Tester · (no e-mail address)'), 'offered, marked, and refused when chosen');

                // Every word has to match, in the name or the address.
                $this->assertSame(['Ann Tester · ann@example.test'], array_values($field->getSearchResults('ann tester')));
                $this->assertSame(['Ann Tester · ann@example.test'], array_values($field->getSearchResults('ann@example')));

                return true;
            });
    }

    public function test_any_contact_can_be_made_a_user_not_only_our_own_staff(): void
    {
        // A customer's contact: what a customer portal needs a login for.
        $customer = Company::create(['company_name' => 'Acme Ltd']);
        $dee = Contact::create(['first_name' => 'Dee', 'last_name' => 'Customer', 'email' => 'dee@acme.test', 'company_id' => $customer->id]);

        Livewire::test(CreateUser::class)
            ->assertFormFieldExists('contact_id', fn (Select $field): bool => $field->getSearchResults('customer') === [$dee->id => 'Dee Customer · dee@acme.test'])
            ->fillForm(['contact_id' => $dee->id, 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(User::query()->where('email', 'dee@acme.test')->value('id'), $dee->refresh()->user_id);
    }

    public function test_editing_a_user_still_shows_its_name_and_email(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.test']);

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->assertFormFieldIsVisible('name')
            ->assertFormFieldIsHidden('contact_id')
            ->fillForm(['name' => 'New Name', 'email' => 'new@example.test'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New Name', $user->refresh()->name);
        $this->assertSame('new@example.test', $user->email);
    }

    protected function contact(string $firstName, ?string $email): Contact
    {
        return Contact::create(['first_name' => $firstName, 'last_name' => 'Tester', 'email' => $email]);
    }
}
