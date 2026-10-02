<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\Pages\CreateMeeting;
use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\Pages\EditMeeting;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\Pages\CreatePhoneCall;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\CreateTask;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\EditTask;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * `Field::crits()` narrows what a relation field offers, but never what the
 * record already links to — Tasks' Employees picker, scoped to your own
 * company's staff, is the case that broke: someone who'd since left showed
 * on the View page and vanished from the Edit form, still linked. They show
 * on the form and can be removed, but aren't offered as a choice.
 */
class RelationCritsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    private Company $home;

    private Contact $colleague;

    private Contact $leaver;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('main'));

        $this->home = Company::create(['company_name' => 'Home']);
        $elsewhere = Company::create(['company_name' => 'Elsewhere']);

        $user = $this->userWithRole('employee');
        Contact::create(['last_name' => 'Me', 'first_name' => 'Ann', 'company_id' => $this->home->id, 'user_id' => $user->id]);
        $this->actingAs(User::find($user->id));

        $this->colleague = Contact::create(['last_name' => 'Colleague', 'first_name' => 'Cal', 'company_id' => $this->home->id]);
        $this->leaver = Contact::create(['last_name' => 'Leaver', 'first_name' => 'Lee', 'company_id' => $elsewhere->id]);
    }

    public function test_create_defaults_employees_to_the_logged_in_user(): void
    {
        Livewire::test(CreateTask::class)
            ->assertSchemaStateSet(['employees' => [(string) Auth::user()->contact->getKey()]]);
    }

    public function test_task_employee_filter_defaults_to_the_logged_in_user(): void
    {
        $fields = collect(TaskResource::fields())->keyBy('name');
        $employees = $fields['employees']->toTableFilter();

        $this->assertNull($fields['permission']->toTableFilter());
        $this->assertNotNull($employees);
        $this->assertTrue($employees->isMultiple());
    }

    public function test_phone_call_create_defaults_employees_to_the_logged_in_user(): void
    {
        Livewire::test(CreatePhoneCall::class)
            ->assertSchemaStateSet(['employees' => [(string) Auth::user()->contact->getKey()]]);
    }

    public function test_meeting_create_defaults_time_and_employees_and_offers_duration_choices(): void
    {
        $this->travelTo(now()->setTime(10, 15));

        $page = Livewire::test(CreateMeeting::class)
            ->assertSchemaStateSet([
                'date' => now()->format('Y-m-d H:i'),
                'employees' => [(string) Auth::user()->contact->getKey()],
            ]);

        $duration = $page->instance()->form->getComponent(
            fn ($component): bool => $component instanceof Select && $component->getName() === 'duration_minutes',
        );

        $this->assertSame([
            15 => '15 min',
            30 => '30 min',
            60 => '1 hour',
            120 => '2 hours',
            240 => '4 hours',
            480 => '8 hours',
        ], $duration->getOptions());
    }

    public function test_meeting_combined_date_time_saves_both_columns_and_reloads_for_edit(): void
    {
        Livewire::test(CreateMeeting::class)
            ->fillForm(['title' => 'Combined time', 'date' => '2026-10-05 23:55:00'])
            ->call('create')
            ->assertHasNoFormErrors();

        $meeting = Meeting::where('title', 'Combined time')->firstOrFail();
        $this->assertSame('2026-10-05', $meeting->date->toDateString());
        $this->assertSame('23:55:00', $meeting->time);

        Livewire::test(EditMeeting::class, ['record' => $meeting->id])
            ->assertSchemaStateSet(['date' => '2026-10-05 23:55'])
            ->fillForm(['date' => '2026-10-06 00:05:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-10-06 00:05:00', $meeting->fresh()->starts_at->toDateTimeString());
    }

    public function test_meeting_combined_date_time_is_required(): void
    {
        Livewire::test(CreateMeeting::class)
            ->fillForm(['title' => 'No date', 'date' => null])
            ->call('create')
            ->assertHasFormErrors(['date' => 'required']);
    }

    public function test_meeting_combined_picker_splits_the_stored_time_after_timezone_conversion(): void
    {
        config(['app.timezone' => 'UTC']);
        FilamentTimezone::set('Europe/Warsaw');
        $meeting = Meeting::create(['title' => 'Timezone', 'date' => '2026-10-05', 'time' => '22:55:00']);
        $meeting->employees()->sync([Auth::user()->contact->id]);

        Livewire::test(EditMeeting::class, ['record' => $meeting->id])
            ->assertSet('data.date', '2026-10-06 00:55:00')
            ->set('data.date', '2026-10-06T01:05')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-10-05 23:05:00', $meeting->fresh()->starts_at->toDateTimeString());
    }

    public function test_a_linked_record_outside_the_crits_can_be_seen_and_removed(): void
    {
        $task = Task::create(['title' => 'Dial', 'permission' => RecordPermission::Public]);
        $task->employees()->sync([$this->colleague->id, $this->leaver->id]);

        Livewire::test(EditTask::class, ['record' => $task->getKey()])
            ->assertSchemaStateSet(['employees' => [(string) $this->colleague->id, (string) $this->leaver->id]])
            ->fillForm(['employees' => [(string) $this->colleague->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$this->colleague->id], $task->employees()->pluck('contacts.id')->all());
    }

    public function test_a_linked_record_outside_the_crits_is_labelled_but_never_offered(): void
    {
        $task = Task::create(['title' => 'Dial', 'permission' => RecordPermission::Public]);
        $task->employees()->sync([$this->colleague->id, $this->leaver->id]);

        $page = Livewire::test(EditTask::class, ['record' => $task->getKey()]);
        $select = $page->instance()->form->getComponent(
            fn ($component): bool => $component instanceof Select && $component->getName() === 'employees',
        );

        $this->assertArrayHasKey($this->leaver->id, $select->getOptionLabels());
        $this->assertArrayNotHasKey($this->leaver->id, $select->getOptions());
        $this->assertArrayHasKey($this->colleague->id, $select->getOptions());
        $this->assertSame([], $select->getSearchResults('Leaver'));
    }

    public function test_meeting_date_time_form_uses_the_selected_calendar(): void
    {
        \Epesi\Modules\RegionalSettings\Models\RegionalSetting::query()->create([
            'user_id' => Auth::id(),
            'timezone' => 'UTC',
            'calendar_system' => 'jalali',
        ]);
        $this->assertSame(
            '2024-03-20 10:15:00',
            \Epesi\Modules\RegionalSettings\Models\RegionalSetting::parseDateTimeInput('۱۴۰۳-۰۱-۰۱ 10:15'),
        );

        $dateField = collect(\Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\MeetingResource::fields())
            ->first(fn ($field): bool => $field->name === 'date');

        $this->assertInstanceOf(
            \Filament\Forms\Components\TextInput::class,
            $dateField->toFormComponent(),
        );
    }

    public function test_task_deadline_form_uses_the_selected_calendar(): void
    {
        \Epesi\Modules\RegionalSettings\Models\RegionalSetting::query()->create([
            'user_id' => Auth::id(),
            'timezone' => 'UTC',
            'calendar_system' => 'jalali',
        ]);

        $deadlineField = collect(\Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource::fields())
            ->first(fn ($field): bool => $field->name === 'deadline');

        $this->assertInstanceOf(
            \Filament\Forms\Components\TextInput::class,
            $deadlineField->toFormComponent(),
        );
    }

    public function test_the_crits_still_keep_new_choices_out(): void
    {
        $task = Task::create(['title' => 'Dial', 'permission' => RecordPermission::Public]);
        $task->employees()->sync([$this->colleague->id]);

        Livewire::test(EditTask::class, ['record' => $task->getKey()])
            ->fillForm(['employees' => [(string) $this->colleague->id, (string) $this->leaver->id]])
            ->call('save')
            ->assertHasFormErrors(['employees.1']);

        $this->assertSame([$this->colleague->id], $task->employees()->pluck('contacts.id')->all());
    }
}
