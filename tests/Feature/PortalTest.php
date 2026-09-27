<?php

namespace Tests\Feature;

use App\Filament\Administration\Resources\Users\Pages\CreateUser;
use App\Filament\Portal\Pages\MyContact;
use App\Models\User;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
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
            'first_name' => 'Joe', 'last_name' => 'Marcozzi', 'email' => 'joe@acme.test',
            'company_id' => $company->id, 'user_id' => $this->customer->id,
        ]);

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

        Livewire::test(MyContact::class)
            ->callAction('edit')
            ->assertSet('editing', true)
            ->assertActionVisible('save')
            ->assertActionVisible('cancel')
            ->assertActionHidden('edit')
            ->assertFormSet(['first_name' => 'Joe', 'last_name' => 'Marcozzi', 'email' => 'joe@acme.test']);
    }

    public function test_cancel_discards_changes_and_returns_to_view_mode(): void
    {
        $this->actingAs($this->customer);

        Livewire::test(MyContact::class)
            ->callAction('edit')
            ->fillForm(['city' => 'Shelbyville'])
            ->callAction('cancel')
            ->assertSet('editing', false)
            ->assertActionVisible('edit');

        $this->assertNotSame('Shelbyville', $this->contact->refresh()->city);
    }

    public function test_saving_updates_the_contact_logs_it_as_the_customers_own_and_returns_to_view_mode(): void
    {
        $this->actingAs($this->customer);

        Livewire::test(MyContact::class)
            ->callAction('edit')
            ->fillForm(['mobile_phone' => '555-0100', 'city' => 'Springfield', 'home_city' => 'Shelbyville'])
            ->callAction('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Saved')
            ->assertSet('editing', false)
            ->assertActionVisible('edit')
            ->assertActionHidden('save')
            ->assertSee('555-0100')
            ->assertSee('Springfield');

        $this->contact->refresh();
        $this->assertSame('555-0100', $this->contact->mobile_phone);
        $this->assertSame('Springfield', $this->contact->city);
        $this->assertSame('Shelbyville', $this->contact->home_city);

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
        $bob = Contact::create(['first_name' => 'Bob', 'last_name' => 'Buyer', 'email' => 'bob@example.test']);

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

    /**
     * @return Collection<int, SentMessage>
     */
    protected function sentMail(): Collection
    {
        return collect(Mail::mailer('array')->getSymfonyTransport()->messages());
    }
}
