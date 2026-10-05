<?php

namespace Tests\Feature;

use App\Filament\Administration\Resources\Users\Pages\CreateUser;
use App\Filament\Portal\Pages\MyContact;
use App\Models\User;
use App\Support\Auth\PortalEmails;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\EmailAddress;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mailer\SentMessage;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The beginning of a customer portal (see AI-shared/Customer-portal.md): a
 * 'customer'-role login opens Administration → Users, which is the main CRM
 * panel, to nobody but the signed-in customer's own contact.
 */
class PortalTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $customer;

    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('customer');

        $company = Company::create(['company_name' => 'Acme Ltd']);
        $this->customer = User::factory()->create(['name' => 'joe', 'email' => 'joe@acme.test']);
        $this->customer->assignRole('customer');
        $this->contact = Contact::create([
            'first_name' => 'Joe', 'last_name' => 'Marcozzi',
            'company_id' => $company->id, 'user_id' => $this->customer->id,
        ]);
        $this->contact->syncCollection('emails', [['kind' => 'work', 'value' => 'joe@acme.test']]);

        Filament::setCurrentPanel('portal');
    }

    public function test_only_a_customer_can_open_the_portal(): void
    {
        $this->actingAs($this->customer)->get(MyContact::getUrl())->assertOk();

        foreach (['super_admin', 'manager', 'employee'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(MyContact::getUrl())
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->create())->get(MyContact::getUrl())->assertForbidden();
    }

    public function test_a_deactivated_customer_is_refused(): void
    {
        $this->customer->update(['active' => false]);

        $this->actingAs($this->customer)->get(MyContact::getUrl())->assertForbidden();
    }

    public function test_a_customer_cannot_open_the_other_panels(): void
    {
        $this->actingAs($this->customer)->get('/')->assertForbidden();
        $this->actingAs($this->customer)->get('/administration')->assertForbidden();
        $this->actingAs($this->customer)->get('/user-settings')->assertForbidden();
    }

    public function test_the_portal_answers_404_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);

        $this->actingAs($this->customer)->get(MyContact::getUrl())->assertNotFound();
    }

    public function test_it_opens_in_view_mode_showing_only_the_signed_in_customers_own_contact(): void
    {
        $this->actingAs($this->customer);

        Livewire::test(MyContact::class)
            ->assertSet('editing', false)
            ->assertActionVisible('edit')
            ->assertActionHidden('save')
            ->assertSee('Marcozzi')
            ->assertSee('Joe')
            ->assertSee('joe@acme.test')
            ->assertSee('Acme Ltd');
    }

    public function test_edit_switches_to_the_form_prefilled_with_the_current_values(): void
    {
        $this->actingAs($this->customer);
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(MyContact::class)
            ->callAction('edit')
            ->assertSet('editing', true)
            ->assertActionVisible('save')
            ->assertActionVisible('cancel')
            ->assertActionHidden('edit')
            ->assertFormSet([
                'first_name' => 'Joe',
                'last_name' => 'Marcozzi',
            ]);

        $undoRepeaterFake();
    }

    public function test_cancel_discards_changes_and_returns_to_view_mode(): void
    {
        $this->actingAs($this->customer);

        $undoRepeaterFake = Repeater::fake();

        Livewire::test(MyContact::class)
            ->callAction('edit')
            ->fillForm(['title' => 'Owner', 'addresses' => [['kind' => 'home', 'city' => 'Shelbyville', 'country' => 'US']]])
            ->callAction('cancel')
            ->assertSet('editing', false)
            ->assertActionVisible('edit');

        $this->assertNull($this->contact->refresh()->title);
        $this->assertCount(0, $this->contact->addresses);

        $undoRepeaterFake();
    }

    public function test_saving_updates_the_contact_logs_it_as_the_customers_own_and_returns_to_view_mode(): void
    {
        $this->actingAs($this->customer);
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(MyContact::class)
            ->callAction('edit')
            ->fillForm([
                'phones' => [['kind' => 'mobile', 'value' => '555-0100', 'messengers' => ['signal']]],
                'addresses' => [
                    ['kind' => 'business', 'city' => 'Springfield', 'country' => 'US'],
                    ['kind' => 'home', 'city' => 'Shelbyville', 'country' => 'US'],
                ],
                'online_accounts' => [['kind' => 'linkedin', 'value' => 'joe-marcozzi']],
            ])
            ->callAction('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Saved')
            ->assertSet('editing', false)
            ->assertActionVisible('edit')
            ->assertActionHidden('save')
            ->assertSee('555-0100')
            ->assertSee('Springfield, United States')
            ->assertSee('Shelbyville, United States');

        $undoRepeaterFake();

        $this->contact->refresh();
        $this->assertSame([['mobile', '555-0100', ['signal']]], $this->contact->phones->map(fn ($phone): array => [$phone->kind, $phone->value, $phone->messengers])->all());
        $this->assertSame(['Springfield', 'Shelbyville'], $this->contact->addresses->pluck('city')->all());
        $this->assertSame(['joe-marcozzi'], $this->contact->online_accounts->pluck('value')->all());

        $activity = Activity::query()->where('subject_type', $this->contact->getMorphClass())->where('subject_id', $this->contact->id)->latest('id')->first();
        $this->assertSame($this->customer->id, $activity->causer_id);
    }

    public function test_company_permission_groups_related_companies_and_the_login_are_not_on_the_form(): void
    {
        $this->actingAs($this->customer);

        $test = Livewire::test(MyContact::class)->callAction('edit');

        foreach (['company_id', 'permission', 'groups', 'relatedCompanies', 'user_id', 'memo'] as $field) {
            $test->assertFormFieldDoesNotExist($field);
        }
    }

    public function test_posting_a_restricted_field_anyway_changes_nothing(): void
    {
        $this->actingAs($this->customer);
        $otherCompany = Company::create(['company_name' => 'Other Ltd']);

        Livewire::test(MyContact::class)
            ->callAction('edit')
            ->set('data.company_id', $otherCompany->id)
            ->set('data.permission', 0)
            ->callAction('save');

        $this->contact->refresh();
        $this->assertNotEquals($otherCompany->id, $this->contact->company_id);
    }

    public function test_a_new_customer_user_is_emailed_a_link_that_opens_the_portal(): void
    {
        $bob = Contact::create(['first_name' => 'Bob', 'last_name' => 'Buyer']);
        $bob->syncCollection('emails', [['kind' => 'work', 'value' => 'bob@example.test']]);

        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $bob->id, 'roles' => [Role::findOrCreate('customer')->id]])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('An e-mail with a link to choose a password was sent to bob@example.test');

        $mail = $this->sentMail()->sole();
        $this->assertStringContainsString('/portal/password-reset/reset', $mail->getOriginalMessage()->getTextBody());
    }

    public function test_the_login_address_is_primary_and_verified_and_cannot_be_removed(): void
    {
        $this->actingAs($this->customer);
        $primary = $this->contact->emails->sole();

        $this->assertSame(
            [['id' => $primary->id, 'value' => 'joe@acme.test', 'primary' => true, 'verified' => true]],
            Livewire::test(MyContact::class)->instance()->emailRows(),
        );

        Livewire::test(MyContact::class)
            ->callAction(TestAction::make('removeEmail')->arguments(['id' => $primary->id]))
            ->assertNotified('The Primary address can not be removed.');

        $this->assertSame(1, $this->contact->refresh()->emails()->count());
    }

    public function test_an_added_address_is_unverified_and_gets_a_verification_link(): void
    {
        $this->actingAs($this->customer);

        Livewire::test(MyContact::class)
            ->callAction('addEmail', ['email' => 'Joe@Home.test'])
            ->assertHasNoActionErrors()
            ->assertNotified('A link to verify Joe@Home.test was sent');

        $added = EmailAddress::query()->where('value', 'joe@home.test')->sole();
        $this->assertNull($added->verified_at);
        $this->assertStringContainsString('/portal-verify-email/'.$added->id.'/', $this->sentMail()->sole()->getOriginalMessage()->getTextBody());

        // Not verified: it can't become the login address.
        $this->assertNotNull(PortalEmails::makePrimary($this->customer, $this->contact, $added->id));
        $this->assertSame('joe@acme.test', $this->customer->refresh()->email);
    }

    public function test_an_address_already_used_by_someone_else_cannot_be_added(): void
    {
        $this->actingAs($this->customer);
        Contact::create(['first_name' => 'Ann', 'last_name' => 'Other'])
            ->syncCollection('emails', [['value' => 'ann@acme.test']]);

        Livewire::test(MyContact::class)
            ->callAction('addEmail', ['email' => 'ann@acme.test'])
            ->assertHasActionErrors(['email']);
    }

    public function test_the_link_verifies_the_address_and_then_it_can_become_the_login(): void
    {
        PortalEmails::add($this->customer, $this->contact, 'joe@home.test');
        $added = EmailAddress::query()->where('value', 'joe@home.test')->sole();

        $url = URL::temporarySignedRoute('portal.verify-email', now()->addHour(), ['email' => $added->id, 'hash' => sha1('joe@home.test')]);
        $this->get($url)->assertRedirect();
        $this->assertNotNull($added->refresh()->verified_at);

        // A tampered link does nothing.
        $other = EmailAddress::query()->where('value', 'joe@acme.test')->sole();
        $other->update(['verified_at' => null]);
        $this->get(route('portal.verify-email', ['email' => $other->id, 'hash' => 'x']))->assertForbidden();
        $this->assertNull($other->refresh()->verified_at);

        $this->assertNull(PortalEmails::makePrimary($this->customer, $this->contact, $added->id));
        $this->assertSame('joe@home.test', $this->customer->refresh()->email);
        $this->assertSame('joe@home.test', $this->contact->refresh()->primaryEmail());

        // The old login address is an ordinary one now: removable.
        $this->assertTrue(PortalEmails::remove($this->customer, $this->contact, $other->id));
    }

    public function test_making_an_address_primary_asks_for_the_password(): void
    {
        $this->customer->update(['password' => 'secret-pass-1']);
        PortalEmails::add($this->customer, $this->contact, 'joe@home.test');
        $added = EmailAddress::query()->where('value', 'joe@home.test')->sole();
        $added->forceFill(['verified_at' => now()])->save();
        $this->actingAs($this->customer);

        $action = TestAction::make('makeEmailPrimary')->arguments(['id' => $added->id]);

        Livewire::test(MyContact::class)
            ->callAction($action, ['password' => 'wrong'])
            ->assertHasActionErrors(['password']);
        $this->assertSame('joe@acme.test', $this->customer->refresh()->email);

        Livewire::test(MyContact::class)
            ->callAction($action, ['password' => 'secret-pass-1'])
            ->assertHasNoActionErrors();
        $this->assertSame('joe@home.test', $this->customer->refresh()->email);
    }

    public function test_changing_an_address_makes_it_unverified_again(): void
    {
        PortalEmails::add($this->customer, $this->contact, 'joe@home.test');
        $added = EmailAddress::query()->where('value', 'joe@home.test')->sole();
        $added->forceFill(['verified_at' => now()])->save();

        $added->update(['value' => 'joe@elsewhere.test']);

        $this->assertNull($added->refresh()->verified_at);
    }

    public function test_a_password_reset_verifies_the_login_address(): void
    {
        $this->customer->forceFill(['email_verified_at' => null])->save();
        $this->assertNull($this->contact->emails->sole()->verified_at);

        event(new PasswordReset($this->customer));

        $this->assertNotNull($this->customer->refresh()->email_verified_at);
        $this->assertNotNull($this->contact->emails()->first()->verified_at);
    }

    /**
     * @return Collection<int, SentMessage>
     */
    protected function sentMail(): Collection
    {
        return collect(Mail::mailer('array')->getSymfonyTransport()->messages());
    }
}
