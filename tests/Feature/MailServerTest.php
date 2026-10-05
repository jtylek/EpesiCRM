<?php

namespace Tests\Feature;

use App\Filament\Administration\Pages\MailServer;
use App\Filament\Administration\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Administration → Mail Server (Epesi's Mail server settings) and the e-mail
 * a new account gets. .env is redirected to a scratch directory, as
 * SetupTest does, so a run never touches the checkout's own.
 */
class MailServerTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected string $scratch;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratch = storage_path('framework/testing/mail-server-'.uniqid());
        File::ensureDirectoryExists($this->scratch);
        File::put($this->scratch.'/.env', "APP_NAME=epesi\nMAIL_MAILER=log\nMAIL_FROM_ADDRESS=old@example.test\n");
        $this->app->useEnvironmentPath($this->scratch);

        $this->admin = $this->userWithRole('super_admin');
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('administration');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->scratch);

        parent::tearDown();
    }

    public function test_only_a_super_admin_can_open_it(): void
    {
        $this->get(MailServer::getUrl())->assertOk();

        $this->actingAs($this->userWithRole('manager'))
            ->get(MailServer::getUrl())
            ->assertForbidden();
    }

    public function test_it_shows_the_current_settings(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'mail.example.test',
            'mail.mailers.smtp.port' => 2525,
            'mail.mailers.smtp.scheme' => 'smtps',
            'mail.from.address' => 'admin@example.test',
        ]);

        Livewire::test(MailServer::class)->assertFormSet([
            'method' => 'smtp',
            'host' => 'mail.example.test',
            'port' => 2525,
            'security' => 'ssl',
            'from_address' => 'admin@example.test',
        ]);
    }

    public function test_saving_an_smtp_server_writes_it_to_env(): void
    {
        Livewire::test(MailServer::class)
            ->fillForm([
                'method' => 'smtp',
                'host' => 'mail.example.test',
                'port' => 2525,
                'security' => 'ssl',
                'username' => 'sender',
                'password' => 'pass word',
                'from_address' => 'admin@example.test',
                'from_name' => 'My CRM',
            ])
            ->callAction('save')
            ->assertNotified('Settings saved')
            ->assertRedirect(MailServer::getUrl());

        $env = File::get($this->scratch.'/.env');

        foreach ([
            'APP_NAME=epesi', // what was there stays
            'MAIL_MAILER=smtp',
            'MAIL_SCHEME=smtps',
            'MAIL_HOST=mail.example.test',
            'MAIL_PORT=2525',
            'MAIL_USERNAME=sender',
            'MAIL_PASSWORD="pass word"',
            'MAIL_FROM_ADDRESS=admin@example.test',
            'MAIL_FROM_NAME="My CRM"',
        ] as $line) {
            $this->assertStringContainsString($line, $env);
        }

        $this->assertStringNotContainsString('old@example.test', $env);
    }

    public function test_the_port_defaults_from_the_security(): void
    {
        $this->saveSmtp(['security' => 'ssl', 'port' => null]);
        $this->assertStringContainsString('MAIL_PORT=465', File::get($this->scratch.'/.env'));

        $this->saveSmtp(['security' => 'tls', 'port' => null]);
        $this->assertStringContainsString('MAIL_PORT=587', File::get($this->scratch.'/.env'));

        $this->saveSmtp(['security' => 'none', 'port' => null]);
        $this->assertStringContainsString('MAIL_PORT=25', File::get($this->scratch.'/.env'));
    }

    public function test_saving_the_servers_own_mail_system_or_no_mail_needs_no_smtp_details(): void
    {
        Livewire::test(MailServer::class)
            ->fillForm(['method' => 'sendmail', 'from_address' => 'admin@example.test'])
            ->callAction('save')
            ->assertHasNoFormErrors();

        $this->assertStringContainsString('MAIL_MAILER=sendmail', File::get($this->scratch.'/.env'));

        Livewire::test(MailServer::class)
            ->fillForm(['method' => 'log', 'from_address' => 'admin@example.test'])
            ->callAction('save')
            ->assertHasNoFormErrors();

        $this->assertStringContainsString('MAIL_MAILER=log', File::get($this->scratch.'/.env'));
    }

    public function test_an_smtp_server_needs_a_host(): void
    {
        Livewire::test(MailServer::class)
            ->fillForm(['method' => 'smtp', 'host' => '', 'from_address' => 'admin@example.test'])
            ->callAction('save')
            ->assertHasFormErrors(['host' => 'required']);
    }

    public function test_an_env_that_cannot_be_written_says_what_to_set_by_hand(): void
    {
        $this->app->useEnvironmentPath($this->scratch.'/missing');

        Livewire::test(MailServer::class)
            ->fillForm(['method' => 'smtp', 'host' => 'mail.example.test', 'password' => 'secret', 'from_address' => 'admin@example.test'])
            ->callAction('save')
            ->assertNotified('The mail settings were not saved')
            ->assertNoRedirect();
    }

    public function test_the_test_sends_an_email_to_the_signed_in_administrator(): void
    {
        // The form says sendmail; the test makes that mailer an array one.
        config(['mail.mailers.sendmail' => ['transport' => 'array']]);

        Livewire::test(MailServer::class)
            ->fillForm(['method' => 'sendmail', 'from_address' => 'admin@example.test'])
            ->callAction('test')
            ->assertNotified('E-mail was sent successfully');

        $message = collect(Mail::mailer('sendmail')->getSymfonyTransport()->messages())->sole()->getOriginalMessage();

        $this->assertSame($this->admin->email, $message->getTo()[0]->getAddress());
        $this->assertSame('admin@example.test', $message->getFrom()[0]->getAddress());
        $this->assertSame('E-mail configuration test', $message->getSubject());

        // Formatted like the other epesi mail, with the logo inline, and a plain-text part.
        $this->assertStringContainsString('<h1', $message->getHtmlBody());
        $this->assertStringContainsString('cid:', $message->getHtmlBody());
        $this->assertCount(1, $message->getAttachments());
        $this->assertStringContainsString('is working properly', $message->getTextBody());
    }

    public function test_the_test_uses_what_the_form_says_without_saving_it(): void
    {
        config(['mail.mailers.sendmail' => ['transport' => 'array']]);

        Livewire::test(MailServer::class)
            ->fillForm(['method' => 'sendmail', 'from_address' => 'admin@example.test'])
            ->callAction('test');

        $this->assertStringContainsString('MAIL_MAILER=log', File::get($this->scratch.'/.env'));
    }

    public function test_the_test_reports_a_server_that_does_not_answer(): void
    {
        config(['mail.mailers.sendmail' => ['transport' => 'array']]);
        Event::listen(MessageSending::class, fn () => throw new TransportException('Connection refused'));

        Livewire::test(MailServer::class)
            ->fillForm(['method' => 'sendmail', 'from_address' => 'admin@example.test'])
            ->callAction('test')
            ->assertNotified('An error has occured');
    }

    public function test_the_test_says_so_when_mail_only_goes_to_the_log(): void
    {
        Livewire::test(MailServer::class)
            ->fillForm(['method' => 'log', 'from_address' => 'admin@example.test'])
            ->callAction('test')
            ->assertNotified('Nothing was sent');

        $this->assertCount(0, $this->sentMail());
    }

    public function test_a_new_user_with_no_password_gets_a_link_to_choose_one(): void
    {
        // As on an install whose .env queues to the database and runs no worker.
        config(['queue.default' => 'database']);

        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $this->contact('Ann', 'ann@example.test')->id, 'roles' => [$this->employeeRoleId()]])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('An e-mail with a link to choose a password was sent to ann@example.test');

        $user = User::query()->where('email', 'ann@example.test')->sole();
        $this->assertNotEmpty($user->password, 'a random one, so the account is never open');
        $this->assertDatabaseCount('jobs', 0);

        $mail = $this->sentMail()->sole();
        $this->assertSame('ann@example.test', $mail->getEnvelope()->getRecipients()[0]->getAddress());
        $this->assertStringContainsString('/password-reset/reset', $mail->getOriginalMessage()->getTextBody());
    }

    public function test_the_link_in_that_email_sets_the_password(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $this->contact('Ann', 'ann@example.test')->id, 'roles' => [$this->employeeRoleId()]])
            ->call('create');

        $mail = $this->sentMail()->sole();

        // The new user isn't signed in, and their link opens the main panel
        // (the administrator created them in Administration).
        Auth::logout();
        $this->assertSame(1, preg_match('#https?://\S+?/password-reset/reset\?[^\s)\]]+#', $mail->getOriginalMessage()->getTextBody(), $link));
        $link = html_entity_decode($link[0]);
        $this->assertStringNotContainsString('/administration/', $link);
        $this->get($link)->assertOk();

        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        Filament::setCurrentPanel('main');
        Livewire::test(ResetPassword::class, ['email' => $query['email'], 'token' => $query['token']])
            ->fillForm(['password' => 'a new password', 'passwordConfirmation' => 'a new password'])
            ->call('resetPassword')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('a new password', User::query()->where('email', 'ann@example.test')->sole()->password));
    }

    public function test_a_user_with_no_role_is_told_why_no_link_was_sent(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $this->contact('Ann', 'ann@example.test')->id])
            ->call('create')
            ->assertNotified('The user was created, but no e-mail was sent');

        $this->assertDatabaseHas('users', ['email' => 'ann@example.test']);
        $this->assertCount(0, $this->sentMail());
    }

    public function test_a_typed_password_is_used_and_nothing_is_sent(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $this->contact('Bob', 'bob@example.test')->id, 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password'])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'bob@example.test')->sole();
        $this->assertTrue(Hash::check('a-long-password', $user->password));
        $this->assertCount(0, $this->sentMail());
    }

    public function test_a_typed_password_still_has_to_be_confirmed(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $this->contact('Bob', 'bob@example.test')->id, 'password' => 'a-long-password', 'password_confirmation' => 'something-else'])
            ->call('create')
            ->assertHasFormErrors(['password']);

        $this->assertDatabaseMissing('users', ['email' => 'bob@example.test']);
    }

    public function test_the_user_is_created_even_when_the_email_cannot_be_sent(): void
    {
        Event::listen(MessageSending::class, fn () => throw new TransportException('Connection refused'));

        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $this->contact('Ann', 'ann@example.test')->id, 'roles' => [$this->employeeRoleId()]])
            ->call('create')
            ->assertNotified('The user was created, but the e-mail could not be sent');

        $this->assertDatabaseHas('users', ['email' => 'ann@example.test']);
    }

    protected function contact(string $firstName, string $email): Contact
    {
        $contact = Contact::create(['first_name' => $firstName, 'last_name' => 'Tester']);
        $contact->syncCollection('emails', [['kind' => 'work', 'value' => $email]]);

        return $contact;
    }

    protected function employeeRoleId(): int
    {
        return Role::findByName('employee')->id;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function saveSmtp(array $overrides): void
    {
        Livewire::test(MailServer::class)
            ->fillForm($overrides + [
                'method' => 'smtp',
                'host' => 'mail.example.test',
                'from_address' => 'admin@example.test',
            ])
            ->callAction('save');
    }

    /**
     * @return Collection<int, SentMessage>
     */
    protected function sentMail(): Collection
    {
        return collect(Mail::mailer('array')->getSymfonyTransport()->messages());
    }
}
