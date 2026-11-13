<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\ActivityReport\ActivityReport;
use Epesi\Modules\ActivityReport\Filament\Pages\UserActivityReport;
use Epesi\Modules\ActivityReport\Filament\RelationManagers\JournalRelationManager;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class ActivityReportTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    /** The "created" entry of a task made by $user. */
    private function taskBy(User $user, string $title): Activity
    {
        $this->actingAs($user);
        $task = Task::create(['title' => $title, 'permission' => RecordPermission::Public]);

        return $task->activities()->where('event', 'created')->sole();
    }

    public function test_the_query_layers_reach_the_created_entry(): void
    {
        $me = $this->userWithRole('employee');
        $mine = $this->taskBy($me, 'My task');

        $this->assertSame('user', $mine->causer_type);
        $this->assertSame([$mine->id], ActivityReport::query([$me->id])->pluck('id')->all(), 'base query');
        $this->assertSame([$mine->id], ActivityReport::apply(ActivityReport::query([$me->id]), ActivityReport::defaultFilter())->pluck('id')->all(), 'default filter');

        $this->actingAs($me);
        $page = Livewire::test(UserActivityReport::class);
        $this->assertSame([$mine->id], $page->instance()->getTableRecords()->pluck('id')->all(), 'page records');
    }

    public function test_an_employee_sees_only_their_own_activity_and_cannot_pick_a_user(): void
    {
        $me = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');

        $mine = $this->taskBy($me, 'My task');
        $theirs = $this->taskBy($colleague, 'Their task');

        $this->actingAs($me);
        $page = Livewire::test(UserActivityReport::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertTableColumnStateSet('record', 'My task', $mine);

        $this->assertNull($page->instance()->getTable()->getFilter('user'), 'an employee has no User filter');
        $this->assertSame([$me->id], ActivityReport::allowedUserIds($me));
    }

    public function test_an_accountant_is_limited_to_their_own_activity_too(): void
    {
        Role::firstOrCreate(['name' => 'accountant']);
        $accountant = $this->userWithRole('accountant');
        $colleague = $this->userWithRole('employee');

        $mine = $this->taskBy($accountant, 'Ledger');
        $theirs = $this->taskBy($colleague, 'Other');

        $this->actingAs($accountant);
        Livewire::test(UserActivityReport::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_a_consultant_has_no_report(): void
    {
        $this->actingAs($this->userWithRole('consultant'));

        $this->assertFalse(UserActivityReport::canAccess());
    }

    public function test_a_manager_sees_their_own_activity_by_default_then_another_user_or_everyone(): void
    {
        $manager = $this->userWithRole('manager');
        $ann = $this->userWithRole('employee');
        $bob = $this->userWithRole('employee');
        $gone = $this->userWithRole('employee', ['active' => false]);

        $mine = $this->taskBy($manager, 'Manager task');
        $annsRow = $this->taskBy($ann, 'Ann task');
        $bobsRow = $this->taskBy($bob, 'Bob task');
        $goneRow = $this->taskBy($gone, 'Inactive task');

        $this->actingAs($manager);
        $page = Livewire::test(UserActivityReport::class);

        // The default: the manager's own.
        $page->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$annsRow, $bobsRow, $goneRow]);

        $page->filterTable('user', $ann->id)
            ->assertCanSeeTableRecords([$annsRow])
            ->assertCanNotSeeTableRecords([$mine, $bobsRow]);

        // Everyone active; whoever has been deactivated is not in the list.
        $page->filterTable('user', UserActivityReport::ALL)
            ->assertCanSeeTableRecords([$mine, $annsRow, $bobsRow])
            ->assertCanNotSeeTableRecords([$goneRow]);

        $options = $page->instance()->getTable()->getFilter('user')->getOptions();
        $this->assertArrayHasKey($ann->id, $options);
        $this->assertArrayNotHasKey($gone->id, $options);
    }

    public function test_a_forged_user_filter_cannot_widen_the_scope(): void
    {
        $manager = $this->userWithRole('manager');
        $gone = $this->userWithRole('employee', ['active' => false]);
        $goneRow = $this->taskBy($gone, 'Inactive task');

        // The scope is the base query: asking for an inactive user's id
        // reaches nothing the manager may not see (inactive users are out).
        $this->assertNotContains($gone->id, ActivityReport::allowedUserIds($manager));

        $this->actingAs($manager);
        Livewire::test(UserActivityReport::class)
            ->filterTable('user', $gone->id)
            ->assertCanNotSeeTableRecords([$goneRow]);
    }

    public function test_the_kind_and_date_filters_narrow_the_report(): void
    {
        $me = $this->userWithRole('employee');
        $created = $this->taskBy($me, 'Budget');

        $task = Task::query()->findOrFail($created->subject_id);
        $task->update(['title' => 'Budget 2027']);
        $edited = $task->activities()->where('event', 'updated')->sole();

        $this->actingAs($me);

        Livewire::test(UserActivityReport::class)
            ->assertCanSeeTableRecords([$created, $edited])
            ->assertTableColumnStateSet('action', 'Created', $created)
            ->assertTableColumnStateSet('action', 'Edited', $edited)
            ->filterTable('report', ['kinds' => ['edit'], 'from' => now()->toDateString(), 'until' => now()->toDateString()])
            ->assertCanSeeTableRecords([$edited])
            ->assertCanNotSeeTableRecords([$created])
            ->filterTable('report', ['kinds' => ['edit', 'new'], 'from' => now()->subDays(10)->toDateString(), 'until' => now()->subDays(5)->toDateString()])
            ->assertCanNotSeeTableRecords([$created, $edited])
            ->filterTable('report', ['kinds' => []])
            ->assertCanNotSeeTableRecords([$created, $edited]);
    }

    public function test_the_journal_tab_is_for_the_contacts_own_user_and_managers(): void
    {
        $ann = $this->userWithRole('employee');
        $bob = $this->userWithRole('employee');
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager);
        $contact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Tester', 'user_id' => $ann->id, 'permission' => RecordPermission::Public]);
        $noLogin = Contact::create(['first_name' => 'No', 'last_name' => 'Login', 'permission' => RecordPermission::Public]);

        $tab = fn (Contact $owner): bool => JournalRelationManager::canViewForRecord($owner, ViewContact::class);

        $this->actingAs($ann);
        $this->assertTrue($tab($contact));

        $this->actingAs($bob);
        $this->assertFalse($tab($contact));

        $this->actingAs($manager);
        $this->assertTrue($tab($contact));
        $this->assertFalse($tab($noLogin), 'a contact without a login has no journal');
    }
}
