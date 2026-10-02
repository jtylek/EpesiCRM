<?php

namespace Tests\Feature;

use App\Enums\RecordPermission;
use App\Enums\RecordStatus;
use App\Filament\Administration\Pages\DemoDataPage;
use App\Filament\Widgets\AgendaWidget;
use App\Models\User;
use App\Support\Demo;
use App\Support\DemoData;
use Database\Seeders\DemoDataSeeder;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\CommonData\Facades\CommonData;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Contacts\Setup\YourCompanyStep;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailLink;
use Epesi\Modules\Mail\Models\MailThread;
use Epesi\Modules\PriorityList\PriorityList;
use Epesi\Modules\RecordBrowser\Browsing\Favorites;
use Epesi\Modules\RecordBrowser\Browsing\RecentRecords;
use Epesi\Modules\Reminders\Models\Reminder;
use Epesi\Modules\Reminders\Reminders;
use Epesi\Modules\Shoutbox\Models\Message;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The demo data the setup wizard can load, and removing it again from
 * Administration → Demo data.
 */
class DemoDataTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $admin;

    protected Company $ownCompany;

    protected Contact $ownContact;

    protected function setUp(): void
    {
        parent::setUp();

        // As after setup: the administrator, with the contact and company
        // from the "Your company" step, then the demo data.
        $this->admin = $this->userWithRole('super_admin');
        $this->actingAs($this->admin);
        $this->ownCompany = Company::create(['company_name' => 'Kowalski Sp. z o.o.', 'permission' => RecordPermission::Public]);
        $this->ownContact = Contact::create(['first_name' => 'Jan', 'last_name' => 'Kowalski', 'company_id' => $this->ownCompany->id, 'user_id' => $this->admin->id]);

        (new DemoDataSeeder)->run($this->admin);
    }

    protected function records(string $model): int
    {
        return $model::query()->withoutGlobalScopes()->count();
    }

    public function test_the_demo_fills_the_lists(): void
    {
        $this->assertSame(101, $this->records(Company::class), '100 demo companies and your own');
        $this->assertSame(101, $this->records(Contact::class));
        $demoContactIds = DB::table('demo_records')->where('table_name', 'contacts')->select('record_id');
        $demoLastNames = Contact::query()->withoutGlobalScopes()->whereIn('id', $demoContactIds)->pluck('last_name');
        $this->assertCount($demoLastNames->unique()->count(), $demoLastNames, 'demo contacts have distinct last names');
        $this->assertSame(30, $this->records(Task::class));
        $this->assertSame(30, $this->records(PhoneCall::class));
        $this->assertSame(5, PhoneCall::query()->withoutGlobalScopes()
            ->whereHas('employees', fn ($query) => $query->where('user_id', $this->admin->id))->count());
        $this->assertSame(30, $this->records(Meeting::class));
        $this->assertSame(0, Meeting::query()->withoutGlobalScopes()->whereDoesntHave('customers')->whereDoesntHave('customerCompanies')->count(), 'every demo meeting has at least one customer');
        $this->assertSame(25, Message::query()->count());
        $this->assertSame(100, $this->records(Attachment::class), 'notes: 7 on the hand-written records, 93 generated');
        $this->assertSame(100, Mail::query()->count());
        $this->assertSame(10, Mail::query()->where('user_id', $this->admin->id)->count());
        $this->assertSame(10, Mail::query()->where('employee_id', $this->ownContact->id)->count());
        $this->assertSame(3, User::query()->count(), 'you, the manager and the employee');

        // Spread over their creators, not all yours.
        $this->assertGreaterThan(1, Company::query()->withoutGlobalScopes()->distinct()->count('created_by'));
        $this->assertSame(0, Task::query()->withoutGlobalScopes()->whereNull('created_by')->count());
        $this->assertTrue(DemoData::present());

        foreach ([Task::class, PhoneCall::class, Meeting::class] as $activity) {
            $this->assertGreaterThan(0, $activity::query()->withoutGlobalScopes()
                ->whereHas('employees', fn ($query) => $query->where('user_id', 1))->count());
        }

        $notesWithFiles = Attachment::query()->withoutGlobalScopes()->whereNotNull('files')->get();
        $files = $notesWithFiles->flatMap(fn (Attachment $note) => $note->storedFiles());
        $this->assertSame(50, $notesWithFiles->filter(fn (Attachment $note) => count($note->files ?? []) > 0)->count());
        $this->assertSame(50, $files->count());
        $this->assertSame(25, $files->filter(fn ($file) => $file->mimeType() === 'image/png')->count());
        $this->assertSame(25, $files->filter(fn ($file) => $file->mimeType() === 'application/pdf')->count());
        $this->assertTrue($files->every(fn ($file) => $file->isOnDisk()));
        $this->assertEqualsCanonicalizing(['pdf', 'png'], $files
            ->map(fn ($file) => strtolower(pathinfo($file->name, PATHINFO_EXTENSION)))->unique()->all());
        $pdf = (string) $files->firstWhere('name', 'demo-note-010.pdf')->read();
        $this->assertStringContainsString('(This is a demo PDF)', $pdf);
        preg_match('/startxref\n(\d+)\n%%EOF\s*$/', $pdf, $pdfCrossReference);
        $this->assertSame('xref', substr($pdf, (int) ($pdfCrossReference[1] ?? -1), 4));

        $pngContents = (string) $files->firstWhere('name', 'demo-note-003.png')->read();
        $image = getimagesizefromstring($pngContents);
        $this->assertNotFalse($image);
        $this->assertSame(IMAGETYPE_PNG, $image[2]);
        $this->assertSame([640, 400], [$image[0], $image[1]]);
        $decodedImage = imagecreatefromstring($pngContents);
        $this->assertNotFalse($decodedImage);
        $this->assertNotSame(imagecolorat($decodedImage, 5, 5), imagecolorat($decodedImage, 5, 100));
        imagedestroy($decodedImage);

        foreach (['company', 'contact', 'task', 'phone_call', 'meeting'] as $recordType) {
            $this->assertCount(10, Favorites::keysFor($this->admin, $recordType), "{$recordType} favorites");
            $this->assertCount(10, RecentRecords::visitsOf($this->admin, $recordType), "{$recordType} recent records");
        }
    }

    public function test_notes_are_on_every_kind_of_record(): void
    {
        foreach (['company', 'contact', 'task', 'phone_call', 'meeting'] as $type) {
            $this->assertGreaterThan(10, DB::table('epesi_attachment_links')->where('attachable_type', $type)->count(), $type);
        }

        $this->assertGreaterThan(1, Attachment::query()->withoutGlobalScopes()->distinct()->count('created_by'), 'by more than one author');
        $this->assertGreaterThan(1, Attachment::query()->withoutGlobalScopes()->selectRaw('date(updated_at)')->distinct()->count(), 'written over more than one day');
        $this->assertGreaterThan(100, strlen(strip_tags((string) Attachment::query()->withoutGlobalScopes()->value('note'))));
        foreach (Attachment::query()->withoutGlobalScopes()->get() as $note) {
            $this->assertGreaterThanOrEqual(100, mb_strlen(strip_tags($note->note)));
            $this->assertStringContainsString('<strong>', $note->note);
            $this->assertStringContainsString('<ul>', $note->note);
        }
    }

    public function test_seeded_activities_appear_in_the_first_users_applets(): void
    {
        $this->assertSame($this->admin->id, $this->ownContact->user_id);
        $this->assertTrue(Task::query()
            ->whereIn('status', array_map(fn (RecordStatus $status): int => $status->value, [RecordStatus::Open, RecordStatus::InProgress, RecordStatus::OnHold]))
            ->whereHas('employees', fn ($query) => $query->where('user_id', $this->admin->id))
            ->exists());
        $this->assertTrue(PhoneCall::query()
            ->whereHas('employees', fn ($query) => $query->where('user_id', $this->admin->id))
            ->whereNotIn('status', array_map(fn (RecordStatus $status): int => $status->value, [RecordStatus::OnHold, ...RecordStatus::finished()]))
            ->where('called_at', '<', today()->addDay())
            ->exists());
        $this->assertTrue(Meeting::query()
            ->whereBetween('date', [today()->toDateString(), today()->addDays(6)->toDateString()])
            ->whereNotIn('status', array_map(fn (RecordStatus $status): int => $status->value, RecordStatus::finished()))
            ->whereHas('employees', fn ($query) => $query->where('user_id', $this->admin->id))
            ->exists());
    }

    public function test_setup_users_agenda_and_reminders_are_populated(): void
    {
        Filament::setCurrentPanel('main');
        $agenda = (new AgendaWidget)->events()->flatten(1);
        $this->assertSame(10, $agenda->where('type', 'Task')->count());
        $this->assertSame(5, $agenda->where('type', 'Phone Call')->count());
        $this->assertSame(5, $agenda->where('type', 'Meeting')->count());
        $mine = fn ($query) => $query->where('user_id', $this->admin->id);
        $this->assertSame(10, Task::whereHas('employees', $mine)->where('status', RecordStatus::Open)->count());
        $this->assertSame(5, PhoneCall::whereHas('employees', $mine)->where('status', RecordStatus::Open)->count());
        $meetings = Meeting::whereHas('employees', $mine)->where('status', RecordStatus::Open)->get();
        $this->assertSame(3, $meetings->filter(fn ($meeting) => $meeting->date->isToday())->count());
        $this->assertSame(2, $meetings->filter(fn ($meeting) => $meeting->date->isTomorrow())->count());
        $reminders = Reminder::activeFor($this->admin)->get();
        $this->assertCount(20, $reminders);
        foreach ($reminders as $reminder) {
            $this->assertFalse($reminder->send_email);
            $this->assertTrue($reminder->remind_at->equalTo(Reminders::startOf($reminder->remindable)->copy()->subMinutes(15)));
        }
        DemoData::remove();
        $this->assertSame(0, Reminder::count());
        $this->assertSame(0, DB::table('epesi_reminder_recipients')->count());
    }

    public function test_installation_before_the_company_step_creates_a_reusable_admin_contact(): void
    {
        DemoData::remove();
        $this->ownContact->forceDelete();
        $this->admin->unsetRelations();
        (new DemoDataSeeder)->run($this->admin);
        $contact = Contact::withoutGlobalScopes()->where('user_id', $this->admin->id)->sole();
        (new YourCompanyStep)->handle([
            'company_name' => 'Setup company',
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ], $this->admin);
        $this->assertSame($contact->id, Contact::withoutGlobalScopes()->where('user_id', $this->admin->id)->sole()->id);
        $this->assertSame(10, Task::whereHas('employees', fn ($query) => $query->whereKey($contact->id))->count());
        $this->assertFalse(DB::table('demo_records')->where('table_name', 'contacts')->where('record_id', $contact->id)->exists());
        DemoData::remove();
        $this->assertNotNull($contact->fresh());
        $this->assertNotNull($this->admin->fresh());
    }

    public function test_public_demo_favorites_and_recent_are_seeded_for_both_demo_users(): void
    {
        DemoData::remove();
        config(['demo.enabled' => true]);
        (new DemoDataSeeder)->run($this->admin);

        foreach (['manager@example.com', 'employee@example.com'] as $email) {
            $user = User::query()->where('email', $email)->sole();

            foreach (['company', 'contact', 'task', 'phone_call', 'meeting'] as $recordType) {
                $this->assertCount(10, Favorites::keysFor($user, $recordType), "{$email}: {$recordType} favorites");
                $this->assertCount(10, RecentRecords::visitsOf($user, $recordType), "{$email}: {$recordType} recent records");
            }

            $this->actingAs($user);
            foreach ([Company::class, Contact::class] as $model) {
                $keys = Favorites::keysFor($user, (new $model)->getMorphClass());
                $this->assertSame(10, $model::whereKey($keys)->count(), 'Favorites must be visible to their user');
                $this->assertSame(10, DB::table('demo_records')->where('table_name', (new $model)->getTable())->whereIn('record_id', $keys)->count());
            }
        }

        foreach (['company', 'contact', 'task', 'phone_call', 'meeting'] as $recordType) {
            $this->assertSame([], Favorites::keysFor($this->admin, $recordType), "administrator: {$recordType} favorites");
            $this->assertSame([], RecentRecords::visitsOf($this->admin, $recordType), "administrator: {$recordType} recent records");
        }
    }

    public function test_every_user_has_ten_next_actions_on_their_priority_list(): void
    {
        foreach (User::query()->get() as $user) {
            $entries = PriorityList::entries($user)->with('record')->get();

            $this->assertCount(PriorityList::LIMIT, $entries, $user->email);
            $this->assertEqualsCanonicalizing(['meeting', 'phone_call', 'task'], $entries->pluck('record_type')->unique()->values()->all(), $user->email);

            foreach ($entries as $entry) {
                $this->assertNotContains($entry->record->status, RecordStatus::finished(), 'only what is still to be done');

                $this->assertTrue($entry->record->employees->contains('user_id', $user->id), "{$user->email}'s own work");
            }
        }

        // Each list is whole for its owner, not only for the administrator seeding it.
        $employee = User::query()->where('email', 'employee@example.com')->sole();
        $this->actingAs($employee);
        $this->assertSame(PriorityList::LIMIT, PriorityList::count($employee));
    }

    public function test_every_group_the_demo_uses_is_on_the_installed_list(): void
    {
        // Epesi's default lists, in its order, created by the modules'
        // migrations. Another module may add groups after them.
        $this->assertSame(['customer', 'vendor', 'other', 'manager'], array_slice(array_keys(CommonData::array('Companies_Groups', 'position')), 0, 4));
        $this->assertSame(['office', 'field', 'customer'], array_slice(array_keys(CommonData::array('Contacts_Groups', 'position')), 0, 3));

        foreach ([Company::class => 'Companies_Groups', Contact::class => 'Contacts_Groups'] as $model => $list) {
            $used = $model::query()->withoutGlobalScopes()->pluck('groups')->flatten()->filter()->unique()->values()->all();

            $this->assertNotSame([], $used);
            $this->assertSame([], array_values(array_diff($used, array_keys(CommonData::array($list)))), "{$list} lacks groups the demo uses");
        }
    }

    public function test_the_demo_e_mails_are_threaded_and_filed_with_their_contacts(): void
    {
        $bruce = Contact::query()->withoutGlobalScopes()->whereHas('emails', fn ($q) => $q->where('value', 'bruce@wayne.test'))->sole();
        $reply = Mail::query()->where('message_id', 'renewal-2@acme.test')->sole();

        $this->assertSame(97, MailThread::query()->count());
        $this->assertSame(3, $reply->thread->message_count);
        $this->assertSame(Mail::OUTGOING, $reply->direction, 'Eli archived her own reply');
        $this->assertSame(3, MailLink::query()->where('linkable_type', 'contact')->where('linkable_id', $bruce->id)->whereIn('mail_id', Mail::query()->where('message_id', 'like', 'renewal-%')->select('id'))->count());
        $this->assertStringContainsString('<strong>training day</strong>', (string) Mail::query()->where('message_id', 'renewal-3@wayne.test')->value('body_html'));
    }

    public function test_it_is_the_same_demo_every_time(): void
    {
        $generated = fn (): array => Company::query()->withoutGlobalScopes()->whereHas('emails', fn ($q) => $q->where('value', 'like', 'office@%'))->orderBy('id')->pluck('company_name')->all();
        $first = $generated();

        $nameStyles = collect($first)->map(fn (string $name): string => match (true) {
            str_starts_with($name, 'The ') => 'the',
            str_ends_with($name, ' Group') => 'group',
            str_ends_with($name, ' Partners') => 'partners',
            str_ends_with($name, ' & Co.') => 'company',
            default => 'industry',
        })->unique();

        DemoData::remove();
        (new DemoDataSeeder)->run($this->admin);

        $this->assertCount(97, $first);
        $this->assertGreaterThan(1, $nameStyles->count(), 'demo companies use varied naming styles');
        $this->assertSame($first, $generated());
    }

    public function test_removing_it_keeps_you_and_what_you_added(): void
    {
        $demoContact = Contact::query()->withoutGlobalScopes()->whereHas('emails', fn ($q) => $q->where('value', 'bruce@wayne.test'))->sole();
        $demoCompany = $demoContact->company;

        // Something real, added after setup, that refers to demo records.
        $ownTask = Task::create(['title' => 'Call the bank', 'permission' => RecordPermission::Public]);
        $ownTask->customers()->attach($demoContact->id);
        $ownTask->customerCompanies()->attach($demoCompany->id);

        DemoData::remove();

        $this->assertSame([$this->admin->id], User::query()->pluck('id')->all());
        $this->assertSame([$this->ownCompany->id], Company::query()->withoutGlobalScopes()->withTrashed()->pluck('id')->all());
        $this->assertSame([$this->ownContact->id], Contact::query()->withoutGlobalScopes()->withTrashed()->pluck('id')->all());
        $this->assertSame([$ownTask->id], Task::query()->withoutGlobalScopes()->withTrashed()->pluck('id')->all());
        $this->assertSame(0, $this->records(PhoneCall::class));
        $this->assertSame(0, $this->records(Meeting::class));
        $this->assertSame(0, Message::query()->count());
        $this->assertSame(0, Attachment::query()->withoutGlobalScopes()->withTrashed()->count());
        $this->assertSame(0, DB::table('epesi_attachment_links')->count());
        $this->assertSame(0, DB::table('epesi_recordbrowser_favorites')->count());
        $this->assertSame(0, DB::table('epesi_recordbrowser_recent')->count());
        $this->assertSame(0, Mail::query()->withTrashed()->count());
        $this->assertSame(0, MailThread::query()->count());
        $this->assertSame(0, DB::table('epesi_mail_links')->count());
        $this->assertSame(0, DB::table('epesi_priority_list_entries')->count(), 'your priority list too');

        // Links to the demo records, and what hung off them, are gone.
        $this->assertSame(0, $ownTask->customers()->count());
        $this->assertSame(0, $ownTask->customerCompanies()->count());
        $this->assertSame(0, Activity::query()->where('subject_type', 'contact')->where('subject_id', $demoContact->id)->count());
        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', '!=', $this->admin->id)->count());
        $this->assertSame(0, DB::table('epesi_watchdog_subscriptions')->whereNot(fn ($q) => $q->where('subscribable_type', 'task')->where('subscribable_id', $ownTask->id)
            ->orWhere(fn ($q) => $q->where('subscribable_type', 'contact')->where('subscribable_id', $this->ownContact->id))
            ->orWhere(fn ($q) => $q->where('subscribable_type', 'company')->where('subscribable_id', $this->ownCompany->id)))->count());

        $this->assertFalse(DemoData::present());
        $this->assertTrue($this->admin->fresh()->hasRole('super_admin'));
    }

    public function test_an_administrator_removes_it_from_the_administration_panel(): void
    {
        $this->get(DemoDataPage::getUrl(panel: 'administration'))
            ->assertOk()
            ->assertSee('This system contains demo data');

        Filament::setCurrentPanel('administration');
        Livewire::test(DemoDataPage::class)
            ->callAction('remove')
            ->assertNotified('The demo data was removed');

        $this->assertSame(1, $this->records(Company::class));
        $this->assertFalse(DemoDataPage::shouldRegisterNavigation(), 'gone from the menu');
    }

    public function test_only_administrators_can(): void
    {
        $this->actingAs($this->userWithRole('manager'));

        $this->get(DemoDataPage::getUrl(panel: 'administration'))->assertForbidden();
    }
}
