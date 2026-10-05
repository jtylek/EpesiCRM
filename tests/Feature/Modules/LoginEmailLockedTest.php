<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\EditContact;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\EmailAddress;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The e-mail address a contact's login signs in with is marked "Login" and
 * can be neither removed nor changed (CollectionItem::isLocked()), wherever
 * the contact is edited; the rest of the addresses behave as always.
 */
class LoginEmailLockedTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['email' => 'joe@acme.test']);
        $this->contact = Contact::create(['first_name' => 'Joe', 'last_name' => 'Marcozzi', 'user_id' => $user->id]);
        $this->contact->syncCollection('emails', [['kind' => 'work', 'value' => 'joe@acme.test'], ['kind' => 'private', 'value' => 'joe@home.test']]);

        $this->actingAs($this->userWithRole('manager'));
        Filament::setCurrentPanel('main');
    }

    public function test_the_view_page_marks_the_login_address(): void
    {
        Livewire::test(ViewContact::class, ['record' => $this->contact->getKey()])
            ->assertSeeText('Login')
            ->assertSeeText('joe@acme.test');

        $this->assertSame(['Login'], EmailAddress::query()->where('value', 'joe@acme.test')->sole()->flags());
        $this->assertSame([], EmailAddress::query()->where('value', 'joe@home.test')->sole()->flags());
    }

    public function test_the_view_page_shows_the_roles_of_the_contacts_login(): void
    {
        $this->contact->user->assignRole('manager');

        Livewire::test(ViewContact::class, ['record' => $this->contact->getKey()])
            ->assertSeeText('Roles')
            ->assertSeeText('Manager');

        $noLogin = Contact::create(['first_name' => 'No', 'last_name' => 'Login']);
        Livewire::test(ViewContact::class, ['record' => $noLogin->getKey()])->assertSeeText('Roles');
    }

    public function test_the_login_address_survives_a_save_that_leaves_it_out_or_changes_it(): void
    {
        $login = EmailAddress::query()->where('value', 'joe@acme.test')->sole();

        $this->contact->syncCollection('emails', [['value' => 'other@home.test']]);
        $this->assertEqualsCanonicalizing(['joe@acme.test', 'other@home.test'], $this->contact->emails()->pluck('value')->all());

        $this->contact->syncCollection('emails', [['id' => $login->id, 'kind' => 'private', 'value' => 'changed@acme.test']]);
        $this->assertSame('joe@acme.test', $login->refresh()->value);
        $this->assertSame('private', $login->kind);
        $this->assertSame(1, $this->contact->emails()->count());
    }

    public function test_the_edit_form_shows_no_delete_for_the_login_address_only(): void
    {
        $undoRepeaterFake = Repeater::fake();
        $login = EmailAddress::query()->where('value', 'joe@acme.test')->sole();

        $test = Livewire::test(EditContact::class, ['record' => $this->contact->getKey()]);
        $seen = 0;

        foreach (array_keys($test->get('data.emails')) as $key) {
            $isLogin = $test->get("data.emails.$key.id") === $login->id;
            $seen++;

            $isLogin
                ? $test->assertFormComponentActionHidden('emails', 'delete', ['item' => $key])
                : $test->assertFormComponentActionVisible('emails', 'delete', ['item' => $key]);
        }

        $undoRepeaterFake();
        $this->assertSame(2, $seen);
    }
}
