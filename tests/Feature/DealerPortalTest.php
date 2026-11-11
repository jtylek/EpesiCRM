<?php

namespace Tests\Feature;

use App\Filament\Administration\Resources\Users\Pages\CreateUser;
use App\Filament\Portal\Pages\MyContact;
use App\Models\User;
use App\Support\Auth\PortalEmails;
use Database\Seeders\RoleSeeder;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\EmailAddress;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The dealer portal's own panel and role (AI-shared/features/access/dealer-portal.md): only a
 * `dealer` opens /dealer, and a dealer opens nothing else. What a dealer sees
 * there comes from modules (Equipment's tests cover its machines).
 */
class DealerPortalTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $dealer;

    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $company = Company::create(['company_name' => 'Alfa Profi']);
        $this->dealer = User::factory()->create(['name' => 'jirka', 'email' => 'jirka@alfa.test']);
        $this->dealer->assignRole('dealer');
        $this->contact = Contact::create([
            'first_name' => 'Jirka', 'last_name' => 'Alfa',
            'company_id' => $company->id, 'user_id' => $this->dealer->id,
        ]);
        $this->contact->syncCollection('emails', [['kind' => 'work', 'value' => 'jirka@alfa.test']]);

        Filament::setCurrentPanel('dealer');
    }

    public function test_only_a_dealer_can_open_the_dealer_portal(): void
    {
        $this->actingAs($this->dealer)->get(MyContact::getUrl(panel: 'dealer'))->assertOk()->assertSee('Alfa');

        foreach (['super_admin', 'manager', 'employee', 'customer'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(MyContact::getUrl(panel: 'dealer'))
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->create())->get(MyContact::getUrl(panel: 'dealer'))->assertForbidden();
    }

    public function test_a_dealer_cannot_open_the_other_panels(): void
    {
        foreach (['/', '/administration', '/user-settings', '/portal'] as $url) {
            $this->actingAs($this->dealer)->get($url)->assertForbidden();
        }
    }

    public function test_a_deactivated_dealer_is_refused(): void
    {
        $this->dealer->update(['active' => false]);

        $this->actingAs($this->dealer)->get(MyContact::getUrl(panel: 'dealer'))->assertForbidden();
    }

    public function test_the_dealer_portal_answers_404_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);

        $this->actingAs($this->dealer)->get(MyContact::getUrl(panel: 'dealer'))->assertNotFound();
    }

    public function test_a_new_dealer_user_is_emailed_a_link_that_opens_the_dealer_portal(): void
    {
        $bob = Contact::create(['first_name' => 'Bob', 'last_name' => 'Servis']);
        $bob->syncCollection('emails', [['kind' => 'work', 'value' => 'bob@alfa.test']]);

        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $bob->id, 'roles' => [Role::findByName('dealer')->id]])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('An e-mail with a link to choose a password was sent to bob@alfa.test');

        $mail = collect(Mail::mailer('array')->getSymfonyTransport()->messages())->sole();
        $this->assertStringContainsString('/dealer/password-reset/reset', $mail->getOriginalMessage()->getTextBody());
    }

    public function test_an_address_verification_link_returns_a_dealer_to_the_dealer_portal(): void
    {
        PortalEmails::add($this->dealer, $this->contact, 'jirka@home.test');
        $added = EmailAddress::query()->where('value', 'jirka@home.test')->sole();

        $url = URL::temporarySignedRoute('portal.verify-email', now()->addHour(), ['email' => $added->id, 'hash' => sha1('jirka@home.test')]);

        $this->get($url)->assertRedirect(MyContact::getUrl(panel: 'dealer'));
        $this->assertNotNull($added->refresh()->verified_at);
    }
}
