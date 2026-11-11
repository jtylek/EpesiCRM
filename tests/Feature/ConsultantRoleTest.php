<?php

namespace Tests\Feature;

use App\Enums\RecordPermission;
use App\Models\User;
use App\Support\Calendar\CalendarRegistry;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ListContacts;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Calendar\TaskCalendarProvider;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ListTasks;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\MailArchiver;
use Epesi\Modules\Reminders\Reminders;
use Epesi\Modules\Watchdog\Watchdog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\Fakes\Eml;
use Tests\TestCase;

/**
 * The consultant role: the main panel, but only the records tied to him —
 * those he created or works on, and the contacts and companies linked to
 * them directly. Public doesn't make a record his.
 */
class ConsultantRoleTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    private User $manager;

    private User $consultant;

    private Contact $me;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('main');

        $this->manager = $this->userWithRole('manager');
        $this->consultant = $this->userWithRole('consultant');

        // Everything is made by the manager, so "he created it" never counts.
        $this->actingAs($this->manager);
        $this->me = Contact::create(['first_name' => 'Con', 'last_name' => 'Sultant', 'user_id' => $this->consultant->id, 'permission' => RecordPermission::Public]);
    }

    public function test_the_consultant_opens_the_main_panel_only(): void
    {
        $this->assertTrue($this->consultant->canAccessPanel(Filament::getPanel('main')));
        $this->assertFalse($this->consultant->canAccessPanel(Filament::getPanel('administration')));
    }

    public function test_he_sees_the_tasks_he_works_on_not_public_ones_nor_those_he_is_only_a_customer_of(): void
    {
        $his = $this->task('His');
        $his->employees()->attach($this->me);
        $public = $this->task('Public');
        $customerOnly = $this->task('Customer only');
        $customerOnly->customers()->attach($this->me);

        $this->actingAs($this->consultant);

        $this->assertSame(['His'], Task::query()->pluck('title')->all());
        Livewire::test(ListTasks::class)
            ->assertCanSeeTableRecords([$his])
            ->assertCanNotSeeTableRecords([$public, $customerOnly]);
    }

    public function test_he_sees_his_meetings_and_phone_calls_only(): void
    {
        $meeting = Meeting::create(['title' => 'His', 'date' => '2026-10-08', 'time' => '10:00:00', 'duration_minutes' => 60, 'permission' => RecordPermission::Public]);
        $meeting->employees()->attach($this->me);
        Meeting::create(['title' => 'Other', 'date' => '2026-10-08', 'time' => '11:00:00', 'duration_minutes' => 60, 'permission' => RecordPermission::Public]);
        $call = PhoneCall::create(['subject' => 'His', 'called_at' => '2026-10-08 10:00:00', 'permission' => RecordPermission::Public]);
        $call->employees()->attach($this->me);
        PhoneCall::create(['subject' => 'Other', 'called_at' => '2026-10-08 11:00:00', 'permission' => RecordPermission::Public]);

        $this->actingAs($this->consultant);

        $this->assertSame(['His'], Meeting::query()->pluck('title')->all());
        $this->assertSame(['His'], PhoneCall::query()->pluck('subject')->all());
    }

    public function test_he_sees_contacts_and_companies_linked_directly_to_his_records(): void
    {
        $acme = $this->company('Acme');
        $beta = $this->company('Beta');
        $gamma = $this->company('Gamma');
        $other = $this->company('Unrelated');
        $ann = $this->contact('Ann', $gamma);
        $bob = $this->contact('Bob');
        $colleague = $this->contact('Colleague');
        $stranger = $this->contact('Stranger', $other);

        $task = $this->task('His');
        $task->employees()->attach([$this->me->id, $colleague->id]);
        $task->customers()->attach($ann);
        $task->customerCompanies()->attach($acme);
        $call = PhoneCall::create(['subject' => 'Call', 'called_at' => '2026-10-08 10:00:00', 'customer_type' => 'company', 'customer_id' => $beta->id]);
        $call->employees()->attach($this->me);
        $bobsCall = PhoneCall::create(['subject' => 'Call Bob', 'called_at' => '2026-10-08 11:00:00', 'customer_type' => 'contact', 'customer_id' => $bob->id]);
        $bobsCall->employees()->attach($this->me);

        $this->actingAs($this->consultant);

        $this->assertEqualsCanonicalizing(
            ['Ann', 'Bob', 'Colleague', 'Con'],
            Contact::query()->pluck('first_name')->all(),
        );
        // Gamma is Ann's company, one step further: not his.
        $this->assertEqualsCanonicalizing(['Acme', 'Beta'], Company::query()->pluck('company_name')->all());

        Livewire::test(ListContacts::class)
            ->call('removeTableFilters')
            ->assertCanSeeTableRecords([$ann, $bob])
            ->assertCanNotSeeTableRecords([$stranger]);
    }

    public function test_he_sees_his_own_company_and_what_he_creates(): void
    {
        $home = $this->company('Home');
        $this->me->update(['company_id' => $home->id]);
        $this->company('Unrelated');

        $this->actingAs($this->consultant);
        $this->assertTrue(Gate::allows('create', Contact::class));
        $this->assertTrue(Gate::allows('create', Company::class));
        $created = Contact::create(['first_name' => 'New', 'last_name' => 'Lead', 'permission' => RecordPermission::Private]);
        Company::create(['company_name' => 'New Co', 'permission' => RecordPermission::Public]);

        $this->assertEqualsCanonicalizing(['Home', 'New Co'], Company::query()->pluck('company_name')->all());
        $this->assertTrue(Contact::query()->whereKey($created->id)->exists());
        $this->assertTrue(Gate::allows('update', $created));
        $this->assertTrue(Gate::allows('delete', $created));
    }

    public function test_he_edits_his_tasks_but_deletes_only_what_he_created(): void
    {
        $his = $this->task('His');
        $his->employees()->attach($this->me);
        $customerOnly = $this->task('Customer only');
        $customerOnly->customers()->attach($this->me);

        $linked = $this->contact('Linked');

        $this->actingAs($this->consultant);
        $own = $this->task('Own');

        $this->assertTrue(Gate::allows('update', $his));
        $this->assertFalse(Gate::allows('delete', $his));
        $this->assertFalse(Gate::allows('update', $customerOnly));
        $this->assertTrue(Gate::allows('update', $own));
        $this->assertTrue(Gate::allows('delete', $own));
        $this->assertFalse(Gate::allows('update', $linked), 'a public contact he did not create is not his to edit');
    }

    public function test_notes_follow_the_records_they_are_attached_to(): void
    {
        $his = $this->task('His');
        $his->employees()->attach($this->me);
        $other = $this->task('Other');

        $onHis = $this->noteOn($his, 'On his task');
        $privateOnHis = $this->noteOn($his, 'Private on his task', RecordPermission::Private);
        $this->noteOn($other, 'On another task');

        $this->actingAs($this->consultant);
        $mine = Attachment::create(['title' => 'Mine, on nothing', 'permission' => RecordPermission::Private]);

        $this->assertEqualsCanonicalizing([$onHis->id, $mine->id], Attachment::query()->pluck('id')->all());
        $this->assertNotContains($privateOnHis->id, Attachment::query()->pluck('id')->all());
    }

    public function test_a_mail_linked_to_his_record_is_not_his_unless_he_sent_or_received_it(): void
    {
        $his = $this->task('His');
        $his->employees()->attach($this->me);
        $this->me->syncCollection('emails', [['kind' => 'work', 'value' => 'con@ourcompany.test']]);

        $archiver = app(MailArchiver::class);
        $archiver->archive(Eml::make(subject: 'Colleagues about his task', messageId: 'a@test'), $this->manager, links: [$his]);
        $archiver->archive(Eml::make(subject: 'Received', to: 'Con <con@ourcompany.test>', messageId: 'b@test'), $this->manager);
        $archiver->archive(Eml::make(subject: 'Sent', from: 'Con <con@ourcompany.test>', messageId: 'c@test'), $this->manager);
        $archiver->archive(Eml::make(subject: 'Copied', cc: 'con@ourcompany.test', messageId: 'd@test'), $this->manager);
        $archiver->archive(Eml::make(subject: 'A longer address', to: 'joe.con@ourcompany.test', messageId: 'e@test'), $this->manager);

        $this->actingAs($this->consultant);
        $archiver->archive(Eml::make(subject: 'Filed by him', messageId: 'f@test'), $this->consultant);

        $this->assertEqualsCanonicalizing(['Received', 'Sent', 'Copied', 'Filed by him'], Mail::query()->pluck('subject')->all());
    }

    public function test_a_shared_mailbox_counts_only_after_his_own_account_has_signed_in_to_it(): void
    {
        $mailbox = ['email' => 'help@ourcompany.test', 'imap_host' => 'imap.test', 'imap_login' => 'help', 'imap_password' => 'secret'];
        $colleagues = MailAccount::create(['user_id' => $this->manager->id, 'name' => 'Help', ...$mailbox]);
        $otherMailbox = MailAccount::create(['user_id' => $this->manager->id, 'name' => 'Boss', ...$mailbox, 'imap_login' => 'boss']);

        $archiver = app(MailArchiver::class);
        $archiver->archive(Eml::make(subject: 'To the help desk', messageId: 'a@test'), $this->manager, $colleagues);
        $archiver->archive(Eml::make(subject: 'To the boss', messageId: 'b@test'), $this->manager, $otherMailbox);

        $this->actingAs($this->consultant);
        $his = MailAccount::create(['user_id' => $this->consultant->id, 'name' => 'Help', ...$mailbox]);

        $this->assertSame([], Mail::query()->pluck('subject')->all(), 'typing in the login proves nothing');

        $his->forceFill(['last_checked_at' => now(), 'last_error' => 'NO authentication failed'])->save();
        $this->assertSame([], Mail::query()->pluck('subject')->all(), 'a refused sign-in proves nothing');

        $his->forceFill(['last_error' => null])->save();
        $this->assertSame(['To the help desk'], Mail::query()->pluck('subject')->all());

        $his->update(['imap_login' => 'boss']);
        $this->assertNull($his->fresh()->last_checked_at, 'a good check says nothing about another mailbox');
        $this->assertSame([], Mail::query()->pluck('subject')->all());
    }

    public function test_he_deletes_only_mail_he_archived_and_an_employee_still_may(): void
    {
        $this->me->syncCollection('emails', [['kind' => 'work', 'value' => 'con@ourcompany.test']]);
        $archiver = app(MailArchiver::class);
        $received = $archiver->archive(Eml::make(subject: 'Received', to: 'con@ourcompany.test', messageId: 'a@test'), $this->manager);
        $filed = $archiver->archive(Eml::make(subject: 'Filed', messageId: 'b@test'), $this->consultant);

        $this->actingAs($this->consultant);
        $this->assertFalse(Gate::allows('delete', $received));
        $this->assertTrue(Gate::allows('delete', $filed));

        $this->actingAs($this->userWithRole('employee'));
        $this->assertTrue(Gate::allows('delete', $received));
    }

    public function test_archiving_a_message_someone_else_filed_keeps_one_copy_and_makes_it_his(): void
    {
        $archiver = app(MailArchiver::class);
        $archiver->archive(Eml::make(subject: 'Shared', messageId: 'a@test'), $this->manager);

        $this->actingAs($this->consultant);
        $this->assertSame([], Mail::query()->pluck('subject')->all());

        $archiver->archive(Eml::make(subject: 'Shared', messageId: 'a@test'), $this->consultant);

        $this->assertSame(1, Mail::query()->withoutGlobalScope('ownership')->count());
        $this->assertSame(['Shared'], Mail::query()->pluck('subject')->all());
    }

    public function test_calendar_watchdog_and_reminders_follow_the_same_rule(): void
    {
        $his = $this->task('His', ['deadline' => '2026-10-08 12:00:00']);
        $his->employees()->attach($this->me);
        $public = $this->task('Public', ['deadline' => '2026-10-08 13:00:00']);

        $this->actingAs($this->consultant);
        $events = CalendarRegistry::eventsBetween(TaskCalendarProvider::class, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-15'), $this->consultant);
        $this->assertSame(['His'], $events->pluck('title')->all());

        $this->actingAs($this->manager);
        $this->assertTrue(Watchdog::canSee($this->consultant, $his));
        $this->assertFalse(Watchdog::canSee($this->consultant, $public));
        $this->assertTrue(Reminders::canSee($this->consultant, $his));
        $this->assertFalse(Reminders::canSee($this->consultant, $public));
    }

    public function test_an_employee_still_sees_every_public_record(): void
    {
        $this->task('Public');
        $this->contact('Public contact');

        $this->actingAs($this->userWithRole('employee'));

        $this->assertSame(['Public'], Task::query()->pluck('title')->all());
        $this->assertContains('Public contact', Contact::query()->pluck('first_name')->all());
    }

    private function task(string $title, array $attributes = []): Task
    {
        return Task::create(['title' => $title, 'permission' => RecordPermission::Public, ...$attributes]);
    }

    private function contact(string $name, ?Company $company = null): Contact
    {
        return Contact::create(['first_name' => $name, 'last_name' => 'Person', 'company_id' => $company?->id, 'permission' => RecordPermission::Public]);
    }

    private function company(string $name): Company
    {
        return Company::create(['company_name' => $name, 'permission' => RecordPermission::Public]);
    }

    private function noteOn(Task $task, string $title, RecordPermission $permission = RecordPermission::Public): Attachment
    {
        $note = Attachment::create(['title' => $title, 'permission' => $permission]);
        $note->links()->create(['attachable_type' => $task->getMorphClass(), 'attachable_id' => $task->id]);

        return $note;
    }
}
