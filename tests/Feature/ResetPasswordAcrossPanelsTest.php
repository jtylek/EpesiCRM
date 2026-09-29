<?php

namespace Tests\Feature;

use App\Filament\Auth\ResetPassword;
use App\Models\User;
use App\Support\Auth\NewAccountMailer;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mailer\SentMessage;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Every panel shares one login session (none declares its own `->authGuard()`),
 * so Filament's reset-password page — which sends an already-signed-in
 * visitor straight into the current panel instead of showing the form — just
 * as readily fires for someone else's link as for your own. Reported as: an
 * administrator, still signed in from Administration, opened a customer's
 * e-mailed link and got a 403 (redirected into the portal, which they can't
 * open) instead of the reset form. App\Filament\Auth\ResetPassword fixes it
 * by signing out first when the signed-in account isn't the link's own.
 */
class ResetPasswordAcrossPanelsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_someone_elses_link_signs_you_out_and_shows_the_form_instead_of_403(): void
    {
        $admin = $this->userWithRole('super_admin');
        $joe = $this->customer('joe@acme.test');

        $link = $this->linkFor($joe);

        $this->actingAs($admin)
            ->get($link)
            ->assertOk()
            ->assertSee('Password', false);

        $this->assertGuest();

        // The link still works, for Joe: signing the wrong visitor out
        // didn't consume or invalidate it.
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        Filament::setCurrentPanel('portal');
        Livewire::test(ResetPassword::class, ['email' => $query['email'], 'token' => $query['token']])
            ->fillForm(['password' => 'a new password', 'passwordConfirmation' => 'a new password'])
            ->call('resetPassword')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('a new password', $joe->refresh()->password));
    }

    public function test_your_own_link_still_signs_you_straight_in_unchanged(): void
    {
        $joe = $this->customer('joe@acme.test');
        $link = $this->linkFor($joe);

        $this->actingAs($joe)
            ->get($link)
            ->assertRedirect('http://localhost/portal');

        $this->assertAuthenticatedAs($joe);
    }

    public function test_a_stranger_opening_a_link_sees_the_form_as_any_guest_would(): void
    {
        $joe = $this->customer('joe@acme.test');

        $this->get($this->linkFor($joe))
            ->assertOk()
            ->assertSee('Password', false);
    }

    protected function customer(string $email): User
    {
        Role::findOrCreate('customer');
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('customer');
        $contact = Contact::create(['first_name' => 'Joe', 'last_name' => 'Buyer', 'user_id' => $user->id]);
        $contact->syncCollection('emails', [['kind' => 'work', 'value' => $email]]);

        return $user;
    }

    protected function linkFor(User $user): string
    {
        NewAccountMailer::sendSetPasswordLink($user);

        $mail = $this->sentMail()->sole();
        $this->assertSame(1, preg_match('#https?://\S+?/password-reset/reset\?[^\s)\]]+#', $mail->getOriginalMessage()->getTextBody(), $link));

        return html_entity_decode($link[0]);
    }

    /**
     * @return Collection<int, SentMessage>
     */
    protected function sentMail(): Collection
    {
        return collect(Mail::mailer('array')->getSymfonyTransport()->messages());
    }
}
