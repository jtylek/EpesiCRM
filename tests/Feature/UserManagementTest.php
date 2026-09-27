<?php

namespace Tests\Feature;

use App\Filament\Administration\Resources\Users\Pages\CreateUser;
use App\Filament\Administration\Resources\Users\Pages\EditUser;
use App\Filament\Administration\Resources\Users\Pages\ListUsers;
use App\Filament\Administration\Resources\Users\Pages\ViewUser;
use App\Models\User;
use App\Support\Auth\NewAccountMailer;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Filament\RelationManagers\HistoryRelationManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Everything about a login is managed in Administration → Users, not on its
 * contact: Reset Password and Change Username live on the user's View page,
 * the contact shows as a badge that links to it, and the user has a History
 * of edits, password changes and role changes (never the password itself).
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $admin;

    protected User $ann;

    protected Contact $annContact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->userWithRole('super_admin', ['name' => 'root']);
        $this->ann = User::factory()->create(['name' => 'annk', 'email' => 'ann@example.test']);
        $this->ann->assignRole('employee');
        $this->annContact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Kowalska', 'email' => 'ann@example.test', 'user_id' => $this->ann->id]);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('administration');
    }

    // ------------------------------------------------------ Moved actions --

    public function test_reset_password_is_on_the_users_view_page(): void
    {
        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->assertActionVisible('resetPassword')
            ->callAction('resetPassword', ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password'])
            ->assertNotified('Password updated');

        $this->assertTrue(Hash::check('a-new-password', $this->ann->refresh()->password));
    }

    public function test_reset_password_wants_eight_characters_that_match(): void
    {
        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->callAction('resetPassword', ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertHasActionErrors(['password']);

        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->callAction('resetPassword', ['password' => 'a-new-password', 'password_confirmation' => 'another-one'])
            ->assertHasActionErrors(['password']);
    }

    public function test_reset_password_left_blank_e_mails_a_link_and_leaves_the_password_as_it_is(): void
    {
        $hash = $this->ann->password;

        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->callAction('resetPassword', ['password' => '', 'password_confirmation' => ''])
            ->assertHasNoActionErrors()
            ->assertNotified('An e-mail with a link to choose a password was sent to ann@example.test');

        $this->assertSame($hash, $this->ann->refresh()->password);

        $mail = $this->sentMail()->sole();
        $this->assertSame('ann@example.test', $mail->getEnvelope()->getRecipients()[0]->getAddress());
        $this->assertStringContainsString('/password-reset/reset', $mail->getOriginalMessage()->getTextBody());

        // In the History: that a link went out, by whom — and no password change yet.
        $this->assertSame($this->admin->id, $this->activities($this->ann)->firstWhere('event', 'password link sent')?->causer_id);
        $this->assertNull($this->activities($this->ann)->firstWhere('event', 'password changed'));
    }

    public function test_a_typed_password_sends_no_e_mail(): void
    {
        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->callAction('resetPassword', ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password']);

        $this->assertCount(0, $this->sentMail());
        $this->assertNull($this->activities($this->ann)->firstWhere('event', 'password link sent'));
    }

    public function test_a_link_is_not_sent_to_an_account_that_cannot_sign_in(): void
    {
        $this->ann->syncRoles([]);

        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->callAction('resetPassword', ['password' => '', 'password_confirmation' => ''])
            ->assertNotified('No e-mail was sent');

        $this->assertCount(0, $this->sentMail());
    }

    public function test_the_reason_no_link_is_sent_is_the_accounts_own(): void
    {
        $this->assertNull(NewAccountMailer::whyNoLink($this->ann), 'an employee can be sent one');

        // 'customer' has its own portal now, so it can be sent one too.
        Role::findOrCreate('customer');
        $this->ann->syncRoles(['customer']);
        $this->assertNull(NewAccountMailer::whyNoLink($this->ann->refresh()));

        // A role that opens no panel at all: it has a role, and still can't sign in anywhere.
        Role::findOrCreate('vendor');
        $this->ann->syncRoles(['vendor']);
        $this->assertSame('Their role (vendor) has nowhere to sign in yet.', NewAccountMailer::whyNoLink($this->ann->refresh()));

        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->callAction('resetPassword', ['password' => '', 'password_confirmation' => ''])
            ->assertNotified('No e-mail was sent');
        $this->assertCount(0, $this->sentMail());

        $this->ann->syncRoles([]);
        $this->assertSame('They have no role.', NewAccountMailer::whyNoLink($this->ann->refresh()));

        $this->ann->syncRoles(['employee']);
        $this->ann->update(['active' => false]);
        $this->assertSame('The account is deactivated.', NewAccountMailer::whyNoLink($this->ann->refresh()));
    }

    public function test_a_customer_can_receive_a_link_that_opens_the_portal(): void
    {
        Role::findOrCreate('customer');
        $this->ann->syncRoles(['customer']);

        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->callAction('resetPassword', ['password' => '', 'password_confirmation' => ''])
            ->assertNotified('An e-mail with a link to choose a password was sent to ann@example.test');

        $mail = $this->sentMail()->sole();
        $this->assertStringContainsString('/portal/password-reset/reset', $mail->getOriginalMessage()->getTextBody());
    }

    public function test_a_customer_login_can_still_be_given_a_typed_password(): void
    {
        Role::findOrCreate('customer');
        $this->ann->syncRoles(['customer']);

        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->callAction('resetPassword', ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password'])
            ->assertNotified('Password updated');

        $this->assertTrue(Hash::check('a-new-password', $this->ann->refresh()->password));
    }

    public function test_a_mail_server_that_does_not_answer_is_reported(): void
    {
        Event::listen(MessageSending::class, fn () => throw new TransportException('Connection refused'));

        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->callAction('resetPassword', ['password' => '', 'password_confirmation' => ''])
            ->assertNotified('The e-mail could not be sent');

        $this->assertNull($this->activities($this->ann)->firstWhere('event', 'password link sent'), 'nothing went out');
    }

    public function test_a_second_link_within_the_minute_is_held_back_and_says_so(): void
    {
        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->callAction('resetPassword', ['password' => '', 'password_confirmation' => '']);

        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->callAction('resetPassword', ['password' => '', 'password_confirmation' => ''])
            ->assertNotified('A link was sent a moment ago');

        $this->assertCount(1, $this->sentMail());
    }

    public function test_change_username_is_on_the_users_view_page_and_starts_from_the_current_one(): void
    {
        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->assertActionVisible('changeUsername')
            ->mountAction('changeUsername')
            ->assertActionDataSet(['email' => 'ann@example.test'])
            ->callMountedAction()
            ->callAction('changeUsername', ['email' => 'ann.k@example.test'])
            ->assertNotified('Username updated');

        $this->assertSame('ann.k@example.test', $this->ann->refresh()->email);
        $this->assertSame('ann@example.test', $this->annContact->refresh()->email, 'the contact\'s own address is left alone');
    }

    public function test_a_username_another_login_has_is_refused(): void
    {
        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->callAction('changeUsername', ['email' => $this->admin->email])
            ->assertHasActionErrors(['email']);

        $this->assertSame('ann@example.test', $this->ann->refresh()->email);
    }

    public function test_a_contact_no_longer_has_a_login_tab_or_those_actions(): void
    {
        Filament::setCurrentPanel('main');

        Livewire::test(ViewContact::class, ['record' => $this->annContact->getKey()])
            ->assertDontSee('Reset Password')
            ->assertDontSee('Change Username')
            ->assertDontSee('Linked User');
    }

    // ------------------------------------------------------ Contact badge --

    public function test_the_contact_is_a_badge_linking_to_it_on_the_view_page_and_in_the_list(): void
    {
        $url = ContactResource::getUrl('view', ['record' => $this->annContact], panel: 'main');

        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->assertSee('Ann Kowalska')
            ->assertSeeHtml('href="'.$url.'"')
            ->assertSeeHtml('fi-badge');

        Livewire::test(ListUsers::class)
            ->assertSeeHtml('href="'.$url.'"');
    }

    public function test_an_account_with_no_contact_shows_its_name_and_no_link(): void
    {
        Livewire::test(ViewUser::class, ['record' => $this->admin->getKey()])
            ->assertSee('root')
            ->assertDontSeeHtml('/contacts/');
    }

    // ------------------------------------------------------------- History --

    public function test_the_view_page_has_a_history_tab(): void
    {
        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->assertSee('History');
    }

    public function test_editing_the_name_email_or_active_flag_is_logged_with_old_and_new_values(): void
    {
        $this->ann->update(['email' => 'ann.k@example.test', 'active' => false]);

        $activity = $this->activities($this->ann)->firstWhere('event', 'updated');
        $this->assertSame($this->admin->id, $activity->causer_id);
        $this->assertSame('ann.k@example.test', $activity->properties['attributes']['email']);
        $this->assertSame('ann@example.test', $activity->properties['old']['email']);

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $this->ann, 'pageClass' => ViewUser::class])
            ->assertSeeHtml('<span class="epesi-history-old">ann@example.test</span> → <span class="epesi-history-new">ann.k@example.test</span>')
            ->assertSeeText('Active: Yes → No');
    }

    public function test_a_password_change_is_logged_and_never_the_password_or_its_hash(): void
    {
        Livewire::test(ViewUser::class, ['record' => $this->ann->getKey()])
            ->callAction('resetPassword', ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password']);

        $activity = $this->activities($this->ann)->firstWhere('event', 'password changed');
        $this->assertNotNull($activity);
        $this->assertSame($this->admin->id, $activity->causer_id);
        $this->assertStringNotContainsString($this->ann->refresh()->password, json_encode($activity->getAttributes()));
        $this->assertStringNotContainsString('a-new-password', json_encode($activity->getAttributes()));

        foreach ($this->activities($this->ann) as $logged) {
            $this->assertArrayNotHasKey('password', $logged->properties['attributes'] ?? []);
        }

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $this->ann, 'pageClass' => ViewUser::class])
            ->assertSeeText('Password changed');
    }

    public function test_a_password_set_from_the_emailed_link_is_logged_as_the_users_own(): void
    {
        // Nobody is signed in: the user follows the link from their e-mail.
        Auth::logout();
        $this->ann->forceFill(['password' => Hash::make('chosen-by-ann')])->save();

        $activity = $this->activities($this->ann)->firstWhere('event', 'password changed');
        $this->assertSame($this->ann->id, $activity->causer_id);
    }

    public function test_signing_in_logs_nothing(): void
    {
        $before = $this->activities($this->ann)->count();

        // What every login and logout does.
        $this->ann->forceFill(['remember_token' => Str::random(60)])->save();

        $this->assertSame($before, $this->activities($this->ann)->count());
    }

    public function test_a_change_of_roles_is_logged(): void
    {
        $manager = Role::findByName('manager')->id;
        $employee = Role::findByName('employee')->id;

        Livewire::test(EditUser::class, ['record' => $this->ann->getKey()])
            ->fillForm(['roles' => [$manager, $employee]])
            ->call('save')
            ->assertHasNoFormErrors();

        $activity = $this->activities($this->ann)->firstWhere('event', 'roles changed');
        $this->assertSame(['employee'], $activity->properties['old']['roles']);
        $this->assertSame(['employee', 'manager'], $activity->properties['attributes']['roles']);

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $this->ann, 'pageClass' => ViewUser::class])
            ->assertSeeText('Roles: employee → employee, manager');
    }

    public function test_saving_with_the_same_roles_logs_no_role_change(): void
    {
        Livewire::test(EditUser::class, ['record' => $this->ann->getKey()])
            ->fillForm(['name' => 'Ann K.'])
            ->call('save');

        $this->assertNull($this->activities($this->ann)->firstWhere('event', 'roles changed'));
    }

    public function test_a_new_users_history_starts_with_who_made_it_for_which_contact_and_with_which_roles(): void
    {
        $bob = Contact::create(['first_name' => 'Bob', 'last_name' => 'Nowak', 'email' => 'bob@example.test']);

        Livewire::test(CreateUser::class)
            ->fillForm(['contact_id' => $bob->id, 'roles' => [Role::findByName('employee')->id], 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password'])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'bob@example.test')->sole();
        $events = $this->activities($user)->pluck('event')->all();

        $this->assertEqualsCanonicalizing(['created', 'contact linked', 'roles changed'], $events);
        $this->assertSame($this->admin->id, $this->activities($user)->firstWhere('event', 'created')->causer_id);
        $this->assertSame('Bob Nowak', $this->activities($user)->firstWhere('event', 'contact linked')->properties['attributes']['contact']);
    }

    /**
     * @return Collection<int, SentMessage>
     */
    protected function sentMail(): Collection
    {
        return collect(Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    /**
     * @return Collection<int, Activity>
     */
    protected function activities(User $user): Collection
    {
        return Activity::query()
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->getKey())
            ->orderBy('id')
            ->get();
    }
}
