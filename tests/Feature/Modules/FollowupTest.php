<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Enums\RecordStatus;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
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
        $this->assertSame($customer->id, $call->contact_id);
        $this->assertSame($company->id, $call->company_id);
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

        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertActionVisible('followup')
            ->callAction('followup', data: ['status' => RecordStatus::Closed->value, 'followup' => 'none'])
            ->assertHasNoActionErrors();

        $this->assertSame(RecordStatus::Closed, $task->fresh()->status);

        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertActionHidden('followup');
    }
}
