<?php

namespace Tests\Feature;

use App\Filament\Auth\RequestPasswordReset;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * "Forgot password?" on the login page: a link by e-mail to a page that sets
 * a new password — and nothing that tells anyone which addresses have an
 * account.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('main');

        // As on an install whose .env still queues to the database and runs
        // no worker: a queued e-mail would wait in the jobs table for good.
        config(['queue.default' => 'database']);
    }

    public function test_the_login_page_links_to_it(): void
    {
        $this->userWithRole(); // else it is off to the setup wizard

        $this->get(route('filament.main.auth.login'))
            ->assertOk()
            ->assertSee(route('filament.main.auth.password-reset.request'), false);

        $this->get(route('filament.main.auth.password-reset.request'))->assertOk();
    }

    public function test_a_user_sets_a_new_password_from_the_emailed_link(): void
    {
        $user = $this->userWithRole('employee', ['email' => 'ann@example.test']);

        $this->requestLinkFor('ann@example.test');

        $this->assertDatabaseCount('jobs', 0);
        $this->assertCount(1, $this->sentMail());
        $mail = $this->sentMail()->sole();
        $this->assertSame('ann@example.test', $mail->getEnvelope()->getRecipients()[0]->getAddress());

        $this->assertSame(1, preg_match('#https?://\S+?/password-reset/reset\?[^\s)\]]+#', $mail->getOriginalMessage()->getTextBody(), $link));
        $link = html_entity_decode($link[0]);
        $this->get($link)->assertOk();

        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        Livewire::test(ResetPassword::class, ['email' => $query['email'], 'token' => $query['token']])
            ->fillForm(['password' => 'a new password', 'passwordConfirmation' => 'a new password'])
            ->call('resetPassword')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertTrue(Hash::check('a new password', $user->refresh()->password));
    }

    public function test_the_email_is_in_the_users_language(): void
    {
        $user = $this->userWithRole('employee', ['email' => 'ann@example.test']);
        RegionalSetting::query()->create(['user_id' => $user->id, 'language' => 'pl']);

        $this->requestLinkFor('ann@example.test');

        $mail = $this->sentMail()->sole()->getOriginalMessage();
        $this->assertSame('Resetowanie hasła', $mail->getSubject());
        $this->assertStringContainsString('Dzień dobry!', $mail->getTextBody());
        $this->assertStringContainsString('Ten link do resetowania hasła wygaśnie za 60 min.', $mail->getTextBody());
    }

    public function test_an_unknown_address_gets_the_same_answer(): void
    {
        $this->requestLinkFor('nobody@example.test')
            ->assertSet('data.email', null);

        $this->assertCount(0, $this->sentMail());
    }

    public function test_asking_again_gets_the_same_answer_and_no_second_email(): void
    {
        $this->userWithRole('employee', ['email' => 'ann@example.test']);

        $this->requestLinkFor('ann@example.test');
        $this->requestLinkFor('ann@example.test');

        $this->assertCount(1, $this->sentMail());
    }

    public function test_a_deactivated_user_gets_no_link(): void
    {
        $this->userWithRole('employee', ['email' => 'ann@example.test', 'active' => false]);

        $this->requestLinkFor('ann@example.test');

        $this->assertCount(0, $this->sentMail());
    }

    public function test_an_email_that_cannot_be_sent_says_so(): void
    {
        $this->userWithRole('employee', ['email' => 'ann@example.test']);
        Event::listen(MessageSending::class, fn () => throw new TransportException('Connection refused'));

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'ann@example.test'])
            ->call('request')
            ->assertSee('The e-mail could not be sent. Ask your administrator to reset your password.');
    }

    /**
     * Asks for a link and checks the answer, which is the same whoever asks.
     */
    protected function requestLinkFor(string $email): mixed
    {
        return Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $email])
            ->call('request')
            ->assertSee("If {$email} belongs to an account, we have sent it a link to reset the password.");
    }

    /**
     * @return Collection<int, SentMessage>
     */
    protected function sentMail(): Collection
    {
        return collect(Mail::mailer('array')->getSymfonyTransport()->messages());
    }
}
