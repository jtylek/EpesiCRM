<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Enums\RecordStatus;
use App\Models\User;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ViewCompany;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ViewTask;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\PriorityList\Filament\Widgets\PriorityListWidget;
use Epesi\Modules\PriorityList\Models\Entry;
use Epesi\Modules\PriorityList\Models\PriorityListPreference;
use Epesi\Modules\PriorityList\PriorityList;
use Epesi\Modules\ProjectsTickets\Models\Project;
use Epesi\Modules\ProjectsTickets\Models\Ticket;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Epesi\Modules\SalesOpportunity\Models\SalesOpportunity;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class PriorityListTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function entry(User $user, Model $record): Entry
    {
        return Entry::query()->where('user_id', $user->id)->whereMorphedTo('record', $record)->sole();
    }

    /**
     * @return array<int, Task>
     */
    protected function fillList(User $user, int $count): array
    {
        return array_map(function (int $i) use ($user): Task {
            $task = Task::create(['title' => "Task {$i}"]);
            $this->assertTrue(PriorityList::add($user, $task));

            return $task;
        }, range(1, $count));
    }

    public function test_the_flag_on_a_task_puts_it_on_my_list_and_takes_it_off(): void
    {
        $me = $this->userWithRole('employee');
        $this->actingAs($me);
        $task = Task::create(['title' => 'Call the bank']);

        $this->get(ViewTask::getUrl(['record' => $task]))
            ->assertOk()
            ->assertSee('Add to priority list');

        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->callAction('priorityListToggle')
            ->assertNotified('Added to your priority list')
            ->assertActionHasLabel('priorityListToggle', 'On priority list');

        $this->assertTrue(PriorityList::has($me, $task));
        $this->assertSame(1, PriorityList::positionOf($me, $task));

        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->callAction('priorityListToggle')
            ->assertNotified('Taken off your priority list');

        $this->assertFalse(PriorityList::has($me, $task));
    }

    public function test_tasks_meetings_and_phone_calls_have_the_flag_and_other_records_do_not(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $types = ['task', 'meeting', 'phone_call'];

        if (class_exists(Project::class)) {
            $types[] = 'project';
        }

        if (class_exists(Ticket::class)) {
            $types[] = 'ticket';
        }

        if (class_exists(SalesOpportunity::class)) {
            $types[] = 'sales_opportunity';
        }

        $this->assertEqualsCanonicalizing($types, PriorityList::recordTypes());

        $company = Company::create(['company_name' => 'ACME']);

        Livewire::test(ViewCompany::class, ['record' => $company->getKey()])
            ->assertActionDoesNotExist('priorityListToggle');
    }

    public function test_priority_list_type_icons_match_their_recordset_icons(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);

        $task = Task::create(['title' => 'Task']);
        $call = PhoneCall::create(['subject' => 'Call', 'called_at' => now(), 'other_customer' => true, 'other_customer_name' => 'X']);
        $meeting = Meeting::create(['title' => 'Meeting', 'date' => '2026-09-30', 'time' => '10:00:00']);

        $this->assertSame(Heroicon::OutlinedCheckCircle, PriorityList::typeIcon($task));
        $this->assertSame(Heroicon::OutlinedPhone, PriorityList::typeIcon($call));
        $this->assertSame(Heroicon::OutlinedCalendarDays, PriorityList::typeIcon($meeting));

        foreach ([$task, $call, $meeting] as $record) {
            PriorityList::add($user, $record);
        }

        Livewire::test(PriorityListWidget::class)
            ->assertSeeHtml('epesi-pl-type-icon')
            ->assertSeeInOrder(['Task · Open', 'Phone call · Open', 'Meeting · Open']);
    }

    public function test_every_recordset_is_a_candidate_but_only_the_registered_ones_start_enabled(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $company = Company::create(['company_name' => 'ACME']);

        // Company has no enableFor() call anywhere, yet it's still a
        // candidate: discovered from its resource in the main panel, no
        // module code needed. It just starts off.
        $this->assertContains('company', PriorityList::candidateTypes());
        $this->assertNotContains('company', PriorityList::recordTypes());
        $this->assertFalse(PriorityList::covers($company));

        PriorityList::setEnabled('company', true);

        $this->assertContains('company', PriorityList::recordTypes());
        $this->assertTrue(PriorityList::covers($company));
        Livewire::test(ViewCompany::class, ['record' => $company->getKey()])
            ->assertActionExists('priorityListToggle');

        PriorityList::setEnabled('company', false);

        $this->assertFalse(PriorityList::covers($company));
        Livewire::test(ViewCompany::class, ['record' => $company->getKey()])
            ->assertActionDoesNotExist('priorityListToggle');
    }

    public function test_an_administrator_can_turn_a_registered_type_off_too(): void
    {
        $this->assertTrue(PriorityList::isEnabled('task'));

        PriorityList::setEnabled('task', false);

        $this->assertFalse(PriorityList::isEnabled('task'));
        $this->assertContains('task', PriorityList::candidateTypes(), 'still a candidate, just off');
        $this->assertNotContains('task', PriorityList::recordTypes());
    }

    public function test_the_list_is_capped_at_ten(): void
    {
        $me = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');
        $this->actingAs($me);

        $tasks = $this->fillList($me, PriorityList::LIMIT);
        $eleventh = Task::create(['title' => 'One too many']);

        $this->assertTrue(PriorityList::isFull($me));
        $this->assertFalse(PriorityList::add($me, $eleventh));
        $this->assertTrue(PriorityList::add($me, $tasks[0]), 'already on it: keeps its place');
        $this->assertSame(PriorityList::LIMIT, Entry::query()->where('user_id', $me->id)->count());

        Livewire::test(ViewTask::class, ['record' => $eleventh->getKey()])
            ->callAction('priorityListToggle')
            ->assertNotified('Your priority list is full!');
        $this->assertFalse(PriorityList::has($me, $eleventh));

        Livewire::test(PriorityListWidget::class)
            ->assertSee('Priority list (10/10)');

        // Everyone has a list of their own.
        $this->actingAs($colleague);
        $this->assertTrue(PriorityList::add($colleague, $eleventh));

        // Finishing something makes room.
        $this->actingAs($me);
        $tasks[0]->update(['status' => RecordStatus::Closed]);
        $this->assertTrue(PriorityList::add($me, $eleventh));
    }

    public function test_a_record_i_can_no_longer_see_takes_no_place_on_my_list(): void
    {
        $me = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');

        $this->actingAs($colleague);
        $shared = Task::create(['title' => 'Shared', 'permission' => RecordPermission::Public]);

        $this->actingAs($me);
        $this->assertTrue(PriorityList::add($me, $shared));
        $this->fillList($me, PriorityList::LIMIT - 1);
        $this->assertTrue(PriorityList::isFull($me));

        $this->actingAs($colleague);
        $shared->update(['permission' => RecordPermission::Private]);

        $this->actingAs($me);
        $this->assertSame(PriorityList::LIMIT - 1, PriorityList::count($me));
        Livewire::test(PriorityListWidget::class)
            ->assertSee('Task 1')
            ->assertDontSee('Shared');

        $this->assertTrue(PriorityList::add($me, Task::create(['title' => 'In its place'])));
        $this->assertFalse(Entry::query()->whereMorphedTo('record', $shared)->exists(), 'dropped to make room');
        $this->assertSame(PriorityList::LIMIT, PriorityList::count($me));
    }

    public function test_the_widget_shows_my_list_in_order_and_every_row_can_be_dragged(): void
    {
        $me = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');
        $this->actingAs($me);

        [$first, $second, $third] = $this->fillList($me, 3);

        $this->actingAs($colleague);
        PriorityList::add($colleague, Task::create(['title' => 'Not mine']));
        PriorityList::add($colleague, $first);
        $theirs = $this->entry($colleague, $first);

        $this->actingAs($me);
        $c = $this->entry($me, $third);

        Livewire::test(PriorityListWidget::class)
            ->assertSeeInOrder(['Task 1', 'Task 2', 'Task 3'])
            ->assertDontSee('Not mine')
            ->assertSee('Priority list (3/10)')
            ->assertSee('Task · Open')
            // Draggable as it stands: no reorder mode to switch on first.
            ->assertSeeHtml('wire:sort.ghost="reorderPriority"')
            ->assertSeeHtml('wire:sort:handle')
            ->call('reorderPriority', $c->getKey(), 0)
            ->assertSeeInOrder(['Task 3', 'Task 1', 'Task 2'])
            ->call('reorderPriority', $c->getKey(), 2)
            ->assertSeeInOrder(['Task 1', 'Task 2', 'Task 3'])
            // Someone else's entry dropped on my list changes nothing.
            ->call('reorderPriority', $theirs->getKey(), 0)
            ->assertSeeInOrder(['Task 1', 'Task 2', 'Task 3']);

        $this->assertSame(3, PriorityList::positionOf($me, $third));
        $this->assertSame(2, $theirs->fresh()->position);

        $this->get('/')->assertOk()->assertSeeLivewire(PriorityListWidget::class);
    }

    public function test_done_closes_the_record_and_takes_it_off_every_list(): void
    {
        $me = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');
        $this->actingAs($me);
        $task = Task::create(['title' => 'Call the bank']);
        PriorityList::add($me, $task);

        $this->actingAs($colleague);
        PriorityList::add($colleague, $task);

        $this->actingAs($me);
        $entry = $this->entry($me, $task);

        $widget = Livewire::test(PriorityListWidget::class)
            ->assertSeeHtml('wire:click="requestComplete('.$entry->getKey().')"')
            ->call('requestComplete', $entry->getKey())
            ->assertActionMounted('confirmCompletion')
            ->assertSchemaComponentExists('suppress_completion_confirmation');

        $this->assertTrue($widget->instance()->mountedActionShouldOpenModal());
        $this->assertSame('Close Task: Call the bank?', $widget->instance()->getMountedAction()->getModalHeading());

        $widget->fillForm(['suppress_completion_confirmation' => false])
            ->callMountedAction()
            ->assertNotified('Done: Task: Call the bank')
            ->assertSee('Your priority list is empty');

        $this->assertSame(RecordStatus::Closed, $task->fresh()->status);
        $this->assertSame(0, Entry::query()->count());
    }

    public function test_do_not_show_again_suppresses_the_modal_only_for_that_user(): void
    {
        $me = $this->userWithRole('employee');
        $this->actingAs($me);

        $first = Task::create(['title' => 'First']);
        PriorityList::add($me, $first);
        $firstEntry = $this->entry($me, $first);

        $widget = Livewire::test(PriorityListWidget::class)
            ->call('requestComplete', $firstEntry->getKey())
            ->assertActionMounted('confirmCompletion');

        $this->assertTrue($widget->instance()->mountedActionShouldOpenModal());

        $widget->fillForm(['suppress_completion_confirmation' => true])
            ->callMountedAction()
            ->assertNotified('Done: Task: First');

        $this->assertTrue(PriorityListPreference::forUser($me)->suppress_completion_confirmation);

        $second = Task::create(['title' => 'Second']);
        PriorityList::add($me, $second);
        $secondEntry = $this->entry($me, $second);

        Livewire::test(PriorityListWidget::class)
            ->call('requestComplete', $secondEntry->getKey())
            ->assertActionNotMounted('confirmCompletion')
            ->assertNotified('Done: Task: Second');

        $colleague = $this->userWithRole('employee');
        $colleaguesTask = Task::create(['title' => 'Colleague task']);
        PriorityList::add($colleague, $colleaguesTask);

        $this->actingAs($colleague);
        Livewire::test(PriorityListWidget::class)
            ->call('requestComplete', $this->entry($colleague, $colleaguesTask)->getKey())
            ->assertActionMounted('confirmCompletion');
    }

    public function test_done_is_offered_only_for_what_i_may_close_and_taking_off_the_list_always(): void
    {
        $me = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');

        $this->actingAs($colleague);
        $readOnly = Task::create(['title' => 'Theirs', 'permission' => RecordPermission::PublicReadOnly]);
        PriorityList::add($colleague, $readOnly);
        $colleaguesEntry = $this->entry($colleague, $readOnly);

        $this->actingAs($me);
        PriorityList::add($me, $readOnly);
        $entry = $this->entry($me, $readOnly);

        Livewire::test(PriorityListWidget::class)
            ->assertDontSeeHtml('completePriority('.$entry->getKey().')')
            ->assertSeeHtml('wire:click="removePriority('.$entry->getKey().')"')
            ->call('completePriority', $entry->getKey())
            ->assertForbidden();

        Livewire::test(PriorityListWidget::class)
            ->call('removePriority', $colleaguesEntry->getKey())
            ->assertNotFound();

        Livewire::test(PriorityListWidget::class)
            ->call('removePriority', $entry->getKey())
            ->assertDontSee('Theirs');

        $this->assertFalse(PriorityList::has($me, $readOnly));
        $this->assertTrue(PriorityList::has($colleague, $readOnly), 'only my own list');
        $this->assertSame(RecordStatus::Open, $readOnly->fresh()->status, 'taking it off the list leaves the record alone');
    }

    public function test_a_record_closed_or_deleted_elsewhere_leaves_every_list(): void
    {
        $me = $this->userWithRole('employee');
        $this->actingAs($me);

        $task = Task::create(['title' => 'Call the bank']);
        $call = PhoneCall::create(['subject' => 'Offer', 'called_at' => '2026-09-27 10:00:00', 'other_customer' => true, 'other_customer_name' => 'X']);
        $meeting = Meeting::create(['title' => 'Review', 'date' => '2026-09-26', 'time' => '10:30:00', 'duration_minutes' => 60]);
        foreach ([$task, $call, $meeting] as $record) {
            PriorityList::add($me, $record);
        }

        $task->update(['title' => 'Call the bank today']);
        $this->assertTrue(PriorityList::has($me, $task), 'any other change keeps it on');

        $task->update(['status' => RecordStatus::Canceled]);
        $call->delete();

        $this->assertFalse(PriorityList::has($me, $task));
        $this->assertFalse(PriorityList::has($me, $call));
        $this->assertTrue(PriorityList::has($me, $meeting));
    }

    public function test_due_dates_are_shown_and_red_once_passed(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25 12:00:00'));
        $this->actingAs($this->userWithRole('employee'));

        $today = Task::create(['title' => 'Today', 'deadline' => '2026-09-25 00:00:00', 'timeless' => true]);
        $late = Task::create(['title' => 'Late', 'deadline' => '2026-09-25 09:00:00']);
        $lateButClosed = Task::create(['title' => 'Done late', 'deadline' => '2026-09-24 09:00:00', 'status' => RecordStatus::Closed]);
        $meeting = Meeting::create(['title' => 'Review', 'date' => '2026-09-26', 'time' => '10:30:00', 'duration_minutes' => 60]);
        $noDeadline = Task::create(['title' => 'Someday']);

        $this->assertSame('2026-09-25', PriorityList::formatDue($today));
        $this->assertFalse(PriorityList::isOverdue($today), 'due until the day ends');
        $this->assertSame('2026-09-25 09:00', PriorityList::formatDue($late));
        $this->assertTrue(PriorityList::isOverdue($late));
        $this->assertFalse(PriorityList::isOverdue($lateButClosed));
        $this->assertSame('2026-09-26 10:30', PriorityList::formatDue($meeting));
        $this->assertNull(PriorityList::formatDue($noDeadline));
    }

    public function test_due_dates_use_the_current_users_regional_date_time_and_timezone(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        RegionalSetting::current()->update([
            'date_format' => 'm/d/Y',
            'time_format' => 'g:i A',
            'timezone' => 'America/Los_Angeles',
        ]);

        $allDay = Task::create(['title' => 'Date only', 'deadline' => '2026-10-05 00:00:00', 'timeless' => true]);
        $timed = Task::create(['title' => 'With time', 'deadline' => '2026-10-05 14:30:00']);

        $this->assertSame('10/05/2026', PriorityList::formatDue($allDay));
        $this->assertSame('10/05/2026 7:30 AM', PriorityList::formatDue($timed));
    }

    public function test_a_row_shows_full_record_details_on_hover(): void
    {
        $me = $this->userWithRole('employee');
        $this->actingAs($me);
        $task = Task::create(['title' => 'Call the bank', 'description' => 'Ask about <b>fees</b>', 'deadline' => '2026-09-25 09:00:00']);
        $bare = Task::create(['title' => 'Someday']);
        PriorityList::add($me, $task);
        PriorityList::add($me, $bare);

        $details = PriorityList::details($task);
        $this->assertStringContainsString('>Task</span>', (string) $details);
        $this->assertStringContainsString('<strong>Call the bank</strong>', (string) $details);
        $this->assertStringContainsString('Ask about &lt;b&gt;fees&lt;/b&gt;', (string) $details);
        $this->assertStringContainsString('Deadline:</strong> 2026-09-25 09:00', (string) $details);
        $this->assertStringContainsString('<strong>Someday</strong>', (string) PriorityList::details($bare));

        Livewire::test(PriorityListWidget::class)
            ->assertSeeHtml(Js::from($details)->toHtml());
    }

    public function test_the_list_lives_on_the_dashboard_only_not_in_the_menu(): void
    {
        $me = $this->userWithRole('employee');
        $this->actingAs($me);
        PriorityList::add($me, Task::create(['title' => 'Call the bank']));

        // Applets load lazily: the page holds the applet, not yet its rows.
        $this->get('/')
            ->assertOk()
            ->assertSeeLivewire(PriorityListWidget::class);

        $this->get('/priority-list')->assertNotFound();
    }
}
