<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Enums\RecordStatus;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\Pages\ListMeetings;
use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\Pages\ViewMeeting;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\Pages\ListPhoneCalls;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\Pages\ViewPhoneCall;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ListTasks;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ViewTask;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\Followup\Followup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class FollowupTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_closing_with_a_follow_up_copies_people_and_leaves_tracing_notes(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $employee = Contact::create(['last_name' => 'Worker', 'first_name' => 'Wendy']);
        $customer = Contact::create(['last_name' => 'Buyer', 'first_name' => 'Bob']);
        $company = Company::create(['company_name' => 'Acme']);

        $task = Task::create(['title' => 'Send offer', 'permission' => RecordPermission::Public]);
        $task->employees()->sync([$employee->id]);
        $task->customers()->sync([$customer->id]);
        $task->customerCompanies()->sync([$company->id]);

        $call = Followup::close($task, RecordStatus::Closed, 'Offer sent', 'phone_call', 'Chase the offer', now()->addDays(3));

        $this->assertSame(RecordStatus::Closed, $task->fresh()->status);
        $this->assertInstanceOf(PhoneCall::class, $call);
        $this->assertSame('Chase the offer', $call->subject);
        // A phone call has one Customer (contact or company); the source
        // task had both, and contact takes priority.
        $this->assertTrue($call->customer->is($customer));
        $this->assertSame([$employee->id], $call->employees()->pluck('contacts.id')->all());

        $this->assertSame(2, $task->attachments()->count(), 'the closing note and the forward trace');
        $this->assertStringContainsString('Follow-up after', $call->attachments()->sole()->note);
    }

    public function test_meeting_follow_up(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $task = Task::create(['title' => 'Plan', 'permission' => RecordPermission::Public]);

        $meeting = Followup::close($task, RecordStatus::Closed, null, 'meeting', null, now()->setDate(2027, 1, 5)->setTime(10, 30));

        $this->assertInstanceOf(Meeting::class, $meeting);
        $this->assertSame('Plan', $meeting->title);
        $this->assertSame('2027-01-05', $meeting->date->toDateString());
    }

    public function test_the_header_action_closes_the_record(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $task = Task::create(['title' => 'Plan', 'permission' => RecordPermission::Public]);

        $dialog = Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->mountAction('followup');

        $action = $dialog->instance()->getMountedAction();
        $this->assertSame(['ctrl+s'], $action->getModalSubmitAction()->getKeyBindings());
        $this->assertSame(['ctrl+e'], $action->getModalCancelAction()->getKeyBindings());

        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertActionVisible('followup')
            ->callAction('followup', data: ['status' => RecordStatus::Closed->value, 'followup' => 'none'])
            ->assertHasNoActionErrors();

        $this->assertSame(RecordStatus::Closed, $task->fresh()->status);

        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertActionHidden('followup');
    }

    public function test_status_badges_open_the_follow_up_dialog_in_lists_and_record_views(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $records = [
            [
                Task::create(['title' => 'Plan', 'permission' => RecordPermission::Public]),
                ListTasks::class,
                ViewTask::class,
            ],
            [
                PhoneCall::create(['subject' => 'Call', 'called_at' => now(), 'permission' => RecordPermission::Public]),
                ListPhoneCalls::class,
                ViewPhoneCall::class,
            ],
            [
                Meeting::create(['title' => 'Meeting', 'date' => now()->toDateString(), 'time' => now()->format('H:i:s'), 'permission' => RecordPermission::Public]),
                ListMeetings::class,
                ViewMeeting::class,
            ],
        ];

        foreach ($records as [$record, $listPage, $viewPage]) {
            Livewire::test($listPage)
                ->mountTableAction('followup', $record)
                ->fillForm(['status' => RecordStatus::Closed->value, 'followup' => 'none'])
                ->callMountedTableAction()
                ->assertHasNoActionErrors();

            $this->assertSame(RecordStatus::Closed, $record->fresh()->status);
            $viewRecord = $record->replicate();
            $viewRecord->status = RecordStatus::Open;
            $viewRecord->save();

            Livewire::test($viewPage, ['record' => $viewRecord->getKey()])
                ->callInfolistAction('status', 'followup', [
                    'status' => RecordStatus::Closed->value,
                    'followup' => 'none',
                ])
                ->assertHasNoInfolistActionErrors();

            $this->assertSame(RecordStatus::Closed, $viewRecord->fresh()->status);
        }
    }

    public function test_adding_a_note_from_the_status_badge_refreshes_the_notes_count(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $task = Task::create(['title' => 'Plan', 'permission' => RecordPermission::Public]);

        $page = Livewire::test(ViewTask::class, ['record' => $task->getKey()]);
        $this->assertSame('0', $this->notesBadgeCount($page->html()));

        $page->callInfolistAction('status', 'followup', [
            'status' => RecordStatus::Closed->value,
            'note' => 'Recorded the outcome',
            'followup' => 'none',
        ])->assertHasNoInfolistActionErrors();

        $this->assertSame(1, $task->attachments()->count());
        $this->assertSame('1', $this->notesBadgeCount($page->html()));
    }

    private function notesBadgeCount(string $html): ?string
    {
        $start = strpos($html, 'data-tab-key="notes::tab"');

        if ($start === false || ($end = strpos($html, '</button>', $start)) === false) {
            return null;
        }

        $tab = substr($html, $start, $end - $start);

        return preg_match('/class="fi-badge[^"]*".*?class="fi-badge-label">([^<]*)</s', $tab, $matches)
            ? trim($matches[1])
            : null;
    }
}
