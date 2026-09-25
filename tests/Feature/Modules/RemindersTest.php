<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\Pages\ViewMeeting;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ViewTask;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\Reminders\Filament\RelationManagers\RemindersRelationManager;
use Epesi\Modules\Reminders\Filament\Widgets\MyRemindersWidget;
use Epesi\Modules\Reminders\Models\Reminder;
use Epesi\Modules\Reminders\Notifications\ReminderMail;
use Epesi\Modules\Reminders\Reminders;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class RemindersTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-24 09:00:00'));
    }

    /**
     * @param  array<int, User>  $recipients
     */
    protected function remind(Model $record, string $at, array $recipients, array $attributes = []): Reminder
    {
        $reminder = $record->reminders()->create(['remind_at' => $at, ...$attributes]);
        $reminder->recipients()->sync(array_map(fn (User $user): int => $user->id, $recipients));

        return $reminder;
    }

    public function test_the_reminders_tab_is_on_tasks_meetings_and_phone_calls(): void
    {
        $this->assertSame(['task', 'meeting', 'phone_call'], Reminders::recordTypes());

        $this->actingAs($this->userWithRole('employee'));
        $task = Task::create(['title' => 'Call the bank', 'deadline' => '2026-09-25 14:00:00']);

        $this->get(ViewTask::getUrl(['record' => $task]))
            ->assertOk()
            ->assertSee('Reminders');
    }

    public function test_a_reminder_before_the_deadline_is_created_from_the_tab(): void
    {
        $me = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');
        $this->actingAs($me);
        $task = Task::create(['title' => 'Call the bank', 'deadline' => '2026-09-25 14:00:00']);

        Livewire::test(RemindersRelationManager::class, ['ownerRecord' => $task, 'pageClass' => ViewTask::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'timing' => 'before',
                'before_amount' => 2,
                'before_unit' => 'hours',
                'recipients' => [$me->id, $colleague->id],
                'message' => 'Have the account number ready',
                'send_email' => true,
            ])
            ->assertHasNoActionErrors();

        $reminder = $task->reminders()->sole();
        $this->assertSame('2026-09-25 12:00:00', $reminder->remind_at->toDateTimeString());
        $this->assertSame(120, $reminder->before_minutes);
        $this->assertSame('2 hours before', $reminder->timingLabel());
        $this->assertTrue($reminder->send_email);
        $this->assertSame($me->id, $reminder->created_by);
        $this->assertEqualsCanonicalizing([$me->id, $colleague->id], $reminder->recipients->modelKeys());

        Livewire::test(RemindersRelationManager::class, ['ownerRecord' => $task, 'pageClass' => ViewTask::class])
            ->assertCanSeeTableRecords([$reminder])
            ->assertSee('Have the account number ready');
    }

    public function test_editing_a_reminder_fills_in_its_offset_and_saves_a_new_one(): void
    {
        $me = $this->userWithRole('employee');
        $this->actingAs($me);
        $task = Task::create(['title' => 'Call the bank', 'deadline' => '2026-09-25 14:00:00']);
        $reminder = $this->remind($task, '2026-09-25 12:00:00', [$me], ['before_minutes' => 120]);

        Livewire::test(RemindersRelationManager::class, ['ownerRecord' => $task, 'pageClass' => ViewTask::class])
            ->mountAction(TestAction::make('edit')->table($reminder))
            ->assertSchemaStateSet(['timing' => 'before', 'before_amount' => 2, 'before_unit' => 'hours'])
            ->fillForm(['before_amount' => 1, 'before_unit' => 'days'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(1440, $reminder->fresh()->before_minutes);
        $this->assertSame('2026-09-24 14:00:00', $reminder->fresh()->remind_at->toDateTimeString());
    }

    public function test_a_fixed_time_reminder_is_created_on_a_meeting(): void
    {
        $me = $this->userWithRole('employee');
        $this->actingAs($me);
        $meeting = Meeting::create(['title' => 'Review', 'date' => '2026-09-26', 'time' => '10:30:00', 'duration_minutes' => 60]);

        Livewire::test(RemindersRelationManager::class, ['ownerRecord' => $meeting, 'pageClass' => ViewMeeting::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'timing' => 'at',
                'remind_at' => '2026-09-25 17:00:00',
                'recipients' => [$me->id],
            ])
            ->assertHasNoActionErrors();

        $reminder = $meeting->reminders()->sole();
        $this->assertSame('2026-09-25 17:00:00', $reminder->remind_at->toDateTimeString());
        $this->assertNull($reminder->before_minutes);
        $this->assertFalse($reminder->send_email);
    }

    public function test_colleagues_who_cannot_see_the_record_cannot_be_reminded_of_it(): void
    {
        $me = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');
        $manager = $this->userWithRole('manager');
        $this->actingAs($me);
        $task = Task::create(['title' => 'Mine', 'permission' => RecordPermission::Private]);

        $this->assertEqualsCanonicalizing([$me->id, $manager->id], Reminders::eligibleRecipients($task)->modelKeys());
        $this->assertSame($me->id, auth()->id(), 'the visibility check restores the signed-in user');
    }

    public function test_relative_reminders_follow_a_rescheduled_record_and_fixed_ones_stay(): void
    {
        $me = $this->userWithRole('employee');
        $this->actingAs($me);
        $meeting = Meeting::create(['title' => 'Review', 'date' => '2026-09-24', 'time' => '09:30:00', 'duration_minutes' => 60]);

        $relative = $this->remind($meeting, '2026-09-24 09:15:00', [$me], ['before_minutes' => 15]);
        $fixed = $this->remind($meeting, '2026-09-24 08:50:00', [$me]);

        // The relative one has already gone off and been turned off.
        $this->assertSame(1, Reminders::deliverDue());
        $this->travelTo(Carbon::parse('2026-09-24 09:20:00'));
        $this->assertSame(1, Reminders::deliverDue());
        $relative->recipientRows()->update(['dismissed_at' => now()]);

        $meeting->update(['date' => '2026-09-25', 'time' => '11:00:00']);

        $this->assertSame('2026-09-25 10:45:00', $relative->fresh()->remind_at->toDateTimeString());
        $this->assertSame('2026-09-24 08:50:00', $fixed->fresh()->remind_at->toDateTimeString());

        // Moved into the future, it is armed again.
        $row = $relative->recipientRows()->sole();
        $this->assertNull($row->sent_at);
        $this->assertNull($row->dismissed_at);

        $task = Task::create(['title' => 'Offer', 'deadline' => '2026-09-30 12:00:00']);
        $onTask = $this->remind($task, '2026-09-29 12:00:00', [$me], ['before_minutes' => 1440]);
        $task->update(['deadline' => '2026-10-02 16:00:00']);
        $this->assertSame('2026-10-01 16:00:00', $onTask->fresh()->remind_at->toDateTimeString());

        $call = PhoneCall::create(['subject' => 'Follow up', 'called_at' => '2026-09-27 10:00:00', 'other_customer' => true, 'other_customer_name' => 'X']);
        $onCall = $this->remind($call, '2026-09-27 09:30:00', [$me], ['before_minutes' => 30]);
        $call->update(['called_at' => '2026-09-28 10:00:00']);
        $this->assertSame('2026-09-28 09:30:00', $onCall->fresh()->remind_at->toDateTimeString());
    }

    public function test_due_reminders_are_delivered_once_and_only_to_recipients_who_can_see_the_record(): void
    {
        $author = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');
        $manager = $this->userWithRole('manager');

        $this->actingAs($author);
        $public = Task::create(['title' => 'Call the bank', 'deadline' => '2026-09-24 10:00:00']);
        $private = Task::create(['title' => 'Secret', 'permission' => RecordPermission::Private]);
        auth()->logout();

        $due = $this->remind($public, '2026-09-24 08:59:00', [$author, $colleague], ['message' => 'Account number!']);
        $this->remind($public, '2026-09-24 09:30:00', [$author]);
        // Private since the reminder was set: the colleague must not learn of it.
        $this->remind($private, '2026-09-24 08:00:00', [$colleague, $manager]);

        $this->assertSame(3, Reminders::deliverDue());

        $this->assertSame(1, $author->notifications()->count());
        $this->assertSame(1, $colleague->notifications()->count());
        $this->assertSame(1, $manager->notifications()->count());

        $data = $author->notifications()->first()->data;
        $this->assertSame('Reminder: Task: Call the bank', $data['title']);
        $this->assertStringContainsString('Account number!', $data['body']);
        $this->assertStringContainsString(ViewTask::getUrl(['record' => $public]), json_encode($data['actions'], JSON_UNESCAPED_SLASHES));
        $this->assertNotNull($due->recipientRows()->where('user_id', $author->id)->value('sent_at'));

        // Only once.
        $this->artisan('reminders:send')->assertSuccessful();
        $this->assertSame(0, Reminders::deliverDue());
        $this->assertSame(1, $author->notifications()->count());

        // The later one, when its time comes.
        $this->travelTo(Carbon::parse('2026-09-24 09:30:00'));
        $this->assertSame(1, Reminders::deliverDue());
        $this->assertSame(2, $author->notifications()->count());
    }

    public function test_a_trashed_record_is_not_reminded_of_and_a_deleted_one_loses_its_reminders(): void
    {
        $me = $this->userWithRole('employee');
        $this->actingAs($me);
        $task = Task::create(['title' => 'Gone']);
        $reminder = $this->remind($task, '2026-09-24 08:00:00', [$me]);

        $task->delete();
        $this->assertSame(0, Reminders::deliverDue());
        $this->assertSame(0, $me->notifications()->count());

        $task->forceDelete();
        $this->assertNull($reminder->fresh());
    }

    public function test_e_mail_goes_out_only_when_asked_for(): void
    {
        Notification::fake();
        $me = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');
        $this->actingAs($me);
        $task = Task::create(['title' => 'Call the bank']);

        $this->remind($task, '2026-09-24 08:00:00', [$me], ['send_email' => true, 'message' => 'Bring coffee']);
        $this->remind($task, '2026-09-24 08:00:00', [$colleague]);

        Reminders::deliverDue();

        Notification::assertSentTo($me, ReminderMail::class, function (ReminderMail $mail) use ($me): bool {
            $message = $mail->toMail($me);

            return $message->subject === 'Reminder: Task: Call the bank'
                && in_array('Bring coffee', $message->introLines, true);
        });
        Notification::assertNotSentTo($colleague, ReminderMail::class);
    }

    public function test_the_widget_lists_my_active_reminders_and_turns_them_off(): void
    {
        $me = $this->userWithRole('employee');
        $other = $this->userWithRole('employee');

        $this->actingAs($other);
        $hidden = Task::create(['title' => 'Not yours', 'permission' => RecordPermission::Private]);

        $this->actingAs($me);
        $task = Task::create(['title' => 'Call the bank']);
        $due = $this->remind($task, '2026-09-24 08:00:00', [$me], ['message' => 'Overdue one']);
        $upcoming = $this->remind($task, '2026-09-25 08:00:00', [$me]);
        $dismissed = $this->remind($task, '2026-09-24 07:00:00', [$me]);
        $dismissed->recipientRows()->update(['dismissed_at' => now()]);
        $someoneElses = $this->remind($task, '2026-09-24 10:00:00', [$other]);
        $invisible = $this->remind($hidden, '2026-09-24 10:00:00', [$me]);

        Livewire::test(MyRemindersWidget::class)
            ->assertCanSeeTableRecords([$due, $upcoming], inOrder: true)
            ->assertCanNotSeeTableRecords([$dismissed, $someoneElses, $invisible])
            ->assertSee('Task: Call the bank')
            ->assertSee('Overdue one')
            ->callTableAction('dismiss', $due)
            ->assertCanNotSeeTableRecords([$due]);

        $this->assertNotNull($due->recipientRows()->sole()->dismissed_at);

        $this->get('/')->assertOk()->assertSeeLivewire(MyRemindersWidget::class);
    }

    public function test_only_the_author_or_a_manager_can_change_a_reminder(): void
    {
        $author = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');
        $manager = $this->userWithRole('manager');
        $this->actingAs($author);
        $task = Task::create(['title' => 'Call the bank']);
        $reminder = $this->remind($task, '2026-09-24 10:00:00', [$author, $colleague]);

        $this->assertTrue($author->can('update', $reminder));
        $this->assertFalse($colleague->can('update', $reminder));
        $this->assertTrue($colleague->can('view', $reminder));
        $this->assertTrue($manager->can('delete', $reminder));
    }
}
