<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\LoginAudit;
use App\Models\User;
use App\Support\Demo;
use Database\Seeders\DemoDataSeeder;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\CreateAttachment;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ListCompanies;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\MailAccountResource;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Demo mode (DEMO_MODE): the login page offers the demo accounts, the
 * Administration panel and the setup wizard are gone, and what would spoil
 * the demo for the next visitor says "Unavailable in demo mode".
 */
class DemoModeTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $admin;

    protected User $manager;

    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->userWithRole('super_admin', ['email' => 'admin@example.com']);
        (new DemoDataSeeder)->run($this->admin);
        $this->manager = User::query()->where('email', 'manager@example.com')->sole();
        $this->employee = User::query()->where('email', 'employee@example.com')->sole();

        config(['demo.enabled' => true]);
        Filament::setCurrentPanel('main');
    }

    public function test_the_login_page_offers_the_demo_accounts_instead_of_a_password(): void
    {
        $this->get(route('filament.main.auth.login'))
            ->assertOk()
            ->assertSee('Log in as')
            ->assertSee('Manager — sees and edits every record')
            ->assertSee('Employee — sees public records and their own')
            ->assertSee('your IP address and browser are recorded')
            ->assertDontSee('data.password', false);
    }

    public function test_there_is_no_password_reset(): void
    {
        $this->get(route('filament.main.auth.login'))->assertDontSee('password-reset');
        $this->get(route('filament.main.auth.password-reset.request'))->assertNotFound();
        $this->get(route('filament.user-settings.auth.password-reset.request'))->assertNotFound();
    }

    public function test_a_visitor_chooses_a_language_that_outlasts_logging_out(): void
    {
        Livewire::test(Login::class)
            ->set('data.locale', 'pl')
            ->assertRedirect(Filament::getLoginUrl());
        $this->assertSame('pl', Cookie::queued(Demo::LOCALE_COOKIE)?->getValue());

        // The browser sends it back: the login page and, once logged in, every
        // page are in Polish, whatever the browser or the shared account says.
        $this->withCookie(Demo::LOCALE_COOKIE, 'pl')
            ->get(route('filament.main.auth.login'), ['Accept-Language' => 'en'])
            ->assertSee('lang="pl"', false);
        $this->actingAs($this->employee)
            ->withCookie(Demo::LOCALE_COOKIE, 'pl')
            ->get('/')
            ->assertSee('lang="pl"', false);

        // Outside demo mode the cookie means nothing.
        config(['demo.enabled' => false]);
        $this->withCookie(Demo::LOCALE_COOKIE, 'pl')
            ->get('/', ['Accept-Language' => 'en'])
            ->assertSee('lang="en"', false);
    }

    public function test_a_visitor_logs_in_as_a_demo_account(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['email' => 'employee@example.com'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertSame($this->employee->id, Auth::id());
    }

    public function test_nobody_else_can_be_chosen(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@example.com'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
        $this->assertGuest();

        // Not even when the list is edited to offer one: never a super_admin.
        config(['demo.users' => [...config('demo.users'), 'admin@example.com' => ['label' => 'Admin', 'description' => 'everything']]]);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@example.com'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
        $this->assertGuest();
    }

    public function test_outside_demo_mode_the_login_page_asks_for_a_password(): void
    {
        config(['demo.enabled' => false]);

        $this->get(route('filament.main.auth.login'))
            ->assertOk()
            ->assertDontSee('Log in as')
            ->assertSee('data.password', false);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'employee@example.com', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertSame($this->employee->id, Auth::id());
    }

    public function test_the_administration_panel_and_the_setup_wizard_are_gone(): void
    {
        $this->actingAs($this->admin);

        $this->get('/administration')->assertNotFound();
        $this->get('/administration/login')->assertNotFound();
        $this->get(route('filament.setup.install'))->assertNotFound();
        $this->assertFalse($this->admin->canAccessPanel(Filament::getPanel('administration')));

        $this->get('/')->assertOk()->assertDontSee('/administration/login-audits');

        config(['demo.enabled' => false]);
        $this->assertTrue($this->admin->canAccessPanel(Filament::getPanel('administration')));
    }

    public function test_the_login_audit_records_the_connection_not_a_header_the_browser_sent(): void
    {
        $this->actingAs($this->employee)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.2'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.77', 'X-Real-IP' => '198.51.100.78'])
            ->get('/')
            ->assertOk();

        $this->assertSame('127.0.0.2', LoginAudit::query()->where('user_id', $this->employee->id)->sole()->ip_address);
    }

    public function test_every_page_says_it_is_a_demo(): void
    {
        $this->actingAs($this->employee)
            ->get('/')
            ->assertOk()
            ->assertSee('This is a demo. The data is reset every day.');
    }

    public function test_bulk_deletes_are_unavailable(): void
    {
        $this->actingAs($this->manager);
        $companies = Company::query()->orderBy('id')->limit(2)->get();

        Livewire::test(ListCompanies::class)
            ->selectTableRecords($companies)
            ->callAction(TestAction::make('delete')->table()->bulk())
            ->assertNotified('Unavailable in demo mode');

        $this->assertSame(2, Company::query()->whereKey($companies->modelKeys())->count(), 'nothing deleted');
    }

    public function test_a_demo_accounts_password_and_username_are_not_offered(): void
    {
        $this->actingAs($this->manager);

        // They are changed in Administration → Users (a user's View page),
        // which doesn't exist in demo mode; a contact no longer offers them.
        Livewire::test(ViewContact::class, ['record' => $this->employee->contact->getKey()])
            ->assertDontSee('Reset Password')
            ->assertDontSee('Change Username');

        $this->assertTrue(Hash::check('password', $this->employee->refresh()->password));
        $this->assertSame('employee@example.com', $this->employee->email);
    }

    public function test_no_files_can_be_uploaded(): void
    {
        $this->actingAs($this->employee);

        Livewire::test(CreateAttachment::class)
            ->assertFormFieldDisabled('files');
    }

    public function test_mail_accounts_are_closed_and_no_mail_leaves_the_server(): void
    {
        $this->actingAs($this->employee);

        $this->assertFalse(MailAccountResource::canAccess());
        $this->get(MailAccountResource::getUrl(panel: 'user-settings'))->assertForbidden();

        Mail::raw('Hello', fn ($message) => $message->to('someone@example.test'));
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());

        config(['demo.enabled' => false]);
        Mail::raw('Hello', fn ($message) => $message->to('someone@example.test'));
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
    }
}
