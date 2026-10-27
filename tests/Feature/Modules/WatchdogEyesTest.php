<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\CreateTask;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\EditTask;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ListTasks;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ViewTask;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\Watchdog\Watchdog;
use Epesi\Modules\Watchdog\Watchers;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The eye in front of each employee of a task — green when they have seen
 * its latest change, orange when not, grey when they don't watch it — and
 * the subscribing that makes it mean something: Epesi's
 * display_contacts_with_notification() and subscribed_employees().
 */
class WatchdogEyesTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    private Company $home;

    private User $ann;

    private User $bob;

    private Contact $annContact;

    private Contact $bobContact;

    private Contact $noLogin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('main'));

        $this->home = Company::create(['company_name' => 'Home']);
        [$this->ann, $this->annContact] = $this->employee('Ann');
        [$this->bob, $this->bobContact] = $this->employee('Bob');
        $this->noLogin = Contact::create(['last_name' => 'Nologin', 'first_name' => 'Nina', 'company_id' => $this->home->id]);
    }

    public function test_creating_a_task_subscribes_its_employees_and_tells_the_others(): void
    {
        $task = $this->createTaskAs($this->ann, [$this->annContact, $this->bobContact, $this->noLogin]);

        $this->assertTrue(Watchdog::isSubscribed($this->ann, $task));
        $this->assertSame(0, Watchdog::unseenCount($this->ann, $task));
        $this->assertTrue(Watchdog::isSubscribed($this->bob, $task));
        $this->assertSame(1, Watchdog::unseenCount($this->bob, $task));

        $this->assertSame(0, $this->ann->notifications()->count());
        $this->assertSame(1, $this->bob->notifications()->count());
        $this->assertSame($this->ann->displayName().' added you to Employees.', $this->bob->notifications()->first()->data['body']);
    }

    public function test_the_eyes_are_green_orange_or_grey_and_turn_green_once_seen(): void
    {
        $task = $this->createTaskAs($this->ann, [$this->annContact, $this->bobContact, $this->noLogin]);

        $this->assertSame(Watchers::SEEN, $this->eye($task, $this->annContact));
        $this->assertSame(Watchers::PENDING, $this->eye($task, $this->bobContact));
        $this->assertNull($this->eye($task, $this->noLogin));

        $this->actingAs($this->bob)->get(ViewTask::getUrl(['record' => $task]))->assertOk();

        $this->assertSame(Watchers::SEEN, $this->eye($task, $this->bobContact));
    }

    public function test_a_change_turns_the_others_eyes_orange(): void
    {
        $task = $this->createTaskAs($this->ann, [$this->annContact, $this->bobContact]);
        Watchdog::markSeen($this->bob, $task);

        $this->actingAs($this->bob);
        Livewire::test(EditTask::class, ['record' => $task->getKey()])
            ->fillForm(['title' => 'Call the bank today'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(Watchers::PENDING, $this->eye($task, $this->annContact));
        $this->assertSame(Watchers::SEEN, $this->eye($task, $this->bobContact));
    }

    public function test_someone_added_on_edit_watches_with_the_latest_change_unseen(): void
    {
        [$cara, $caraContact] = $this->employee('Cara');
        $task = $this->createTaskAs($this->ann, [$this->annContact]);

        $this->actingAs($this->ann);
        Livewire::test(EditTask::class, ['record' => $task->getKey()])
            ->fillForm(['employees' => [(string) $this->annContact->id, (string) $caraContact->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(Watchers::PENDING, $this->eye($task, $caraContact));
        $this->assertSame(1, $cara->notifications()->count());

        // Taken off again, they keep watching, as in Epesi.
        Livewire::test(EditTask::class, ['record' => $task->getKey()])
            ->fillForm(['employees' => [(string) $this->annContact->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Watchdog::isSubscribed($cara, $task));
    }

    public function test_a_watcher_who_cannot_see_the_record_is_not_left_behind(): void
    {
        $outsider = $this->userWithRole('employee', ['name' => 'Otto']);
        $task = $this->createTaskAs($this->ann, [$this->annContact], RecordPermission::Private);
        Watchdog::subscribe($outsider, $task);

        $this->actingAs($this->ann);
        $task->update(['title' => 'Call the bank today']);

        $this->assertFalse(Watchdog::canSee($outsider, $task));
        $this->assertSame(0, $outsider->notifications()->count());
        $this->assertSame(0, Watchdog::unseenCount($outsider, $task));
    }

    public function test_the_list_draws_the_eyes_in_a_fixed_number_of_queries(): void
    {
        $tasks = new Collection(collect(range(1, 5))->map(fn (int $i): Task => $this->createTaskAs($this->ann, [$this->annContact, $this->bobContact], title: "Task {$i}"))->all());
        $tasks->load('employees');
        Watchers::forget();

        DB::enableQueryLog();
        Watchers::prepare($this->employeesField(), $tasks);
        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();

        $this->actingAs($this->ann);
        Livewire::test(ListTasks::class)
            ->assertSeeHtml('epesi-watcher-eye')
            ->assertSee('This person has notifications pending about changes made to this record.')
            ->assertSee('This person is up to date with all changes made to this record.');
    }

    public function test_record_info_lists_the_watchers_with_their_state(): void
    {
        $task = $this->createTaskAs($this->ann, [$this->annContact, $this->bobContact]);

        $rows = Watchers::recordInfoRows($task);

        $this->assertCount(2, $rows);
        $this->assertSame('Watching', $rows[0][0]);
        $this->assertStringContainsString('Ann', $rows[0][1]->toHtml());
        $this->assertSame('Up to date', $rows[0][2]);
        $this->assertStringContainsString('Bob', $rows[1][1]->toHtml());
        $this->assertSame('Has changes not yet seen', $rows[1][2]);
    }

    public function test_only_fields_marked_with_watchers_count(): void
    {
        $task = $this->createTaskAs($this->ann, [$this->annContact]);

        $this->assertSame(['employees'], array_map(fn (Field $field): string => $field->name, Watchers::fieldsOf($task)));
        $this->assertSame([], Watchers::fieldsOf($this->home), 'a company has no Employees field');
    }

    public function test_saving_a_model_with_no_morph_alias_is_left_alone(): void
    {
        $model = new class extends Model {};

        $this->assertFalse(Watchdog::watches($model));
        Watchers::subscribe($model, $this->ann);
    }

    /** @return array{0: User, 1: Contact} */
    private function employee(string $name): array
    {
        $user = User::find($this->userWithRole('employee', ['name' => $name])->id);
        $contact = Contact::create(['last_name' => $name, 'first_name' => $name, 'company_id' => $this->home->id, 'user_id' => $user->id]);

        return [$user->fresh(), $contact];
    }

    /** @param  array<int, Contact>  $employees */
    private function createTaskAs(User $author, array $employees, RecordPermission $permission = RecordPermission::Public, string $title = 'Call the bank'): Task
    {
        $this->actingAs($author);

        Livewire::test(CreateTask::class)
            ->fillForm([
                'title' => $title,
                'permission' => $permission,
                'employees' => array_map(fn (Contact $contact): string => (string) $contact->id, $employees),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        return Task::query()->withoutGlobalScopes()->where('title', $title)->latest('id')->firstOrFail();
    }

    private function employeesField(): Field
    {
        return collect(TaskResource::resolvedFields())->firstWhere('name', 'employees');
    }

    private function eye(Task $task, Contact $contact): ?string
    {
        Watchers::forget();
        $html = Watchers::badge($this->employeesField(), $task, $contact)->toHtml();

        return match (true) {
            str_contains($html, 'up to date') => Watchers::SEEN,
            str_contains($html, 'notifications pending') => Watchers::PENDING,
            default => null,
        };
    }
}
