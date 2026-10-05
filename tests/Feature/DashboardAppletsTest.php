<?php

namespace Tests\Feature;

use App\Enums\RecordStatus;
use App\Filament\Pages\Calendar as CalendarPage;
use App\Filament\Widgets\AgendaWidget;
use App\Models\User;
use Closure;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Filament\Widgets\PhoneCallsWidget;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\CRM\Tasks\Filament\Widgets\TasksWidget;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * What the Tasks, Phone calls and Agenda applets list under their settings,
 * as their legacy applets (CRM_Tasks, CRM_PhoneCall, CRM_Calendar) did.
 */
class DashboardAppletsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    private User $user;

    private Contact $me;

    private Contact $colleague;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-24 09:00:00'));

        $this->user = $this->userWithRole('employee');
        $this->me = Contact::create(['first_name' => 'Me', 'last_name' => 'Myself']);
        $this->me->forceFill(['user_id' => $this->user->id])->save();
        $this->colleague = Contact::create(['first_name' => 'Col', 'last_name' => 'League']);

        $this->actingAs($this->user);
    }

    public function test_recordset_applet_defaults_keep_the_header_and_actions_shared(): void
    {
        $widget = Livewire::test(TasksWidget::class, [
            'appletId' => 123,
            'appletSettings' => ['subtitle' => '<Sales>'],
        ])->assertSuccessful();

        $table = $widget->instance()->getTable();
        $heading = $table->getHeading()->toHtml();

        $this->assertStringContainsString('epesi-applet-heading', $heading);
        $this->assertStringContainsString('Tasks - &lt;Sales&gt;', $heading);
        $this->assertStringNotContainsString('<Sales>', $heading);
        $this->assertSame([5, 10, 25], $table->getPaginationPageOptions());
        $this->assertSame(10, $table->getDefaultPaginationPageOption());
        $this->assertSame(['create', 'fullscreen', 'configureApplet'], array_values(array_map(
            fn ($action): string => $action->getName(),
            $table->getHeaderActions(),
        )));
    }

    public function test_tasks_show_the_open_ones_assigned_to_me_soonest_first(): void
    {
        $this->task('Later', '2026-09-30 10:00:00', employee: $this->me);
        $this->task('Sooner', '2026-09-25 10:00:00', employee: $this->me);
        $this->task('No deadline', null, employee: $this->me);
        $this->task('Done', '2026-09-24 10:00:00', employee: $this->me, status: RecordStatus::Closed);
        $this->task('I am the customer', '2026-09-24 10:00:00', employee: $this->colleague, customer: $this->me);
        $this->task('Not mine', '2026-09-24 11:00:00', employee: $this->colleague);

        $this->assertSame(['Sooner', 'Later', 'No deadline'], $this->tasks([]));
        $this->assertSame(['I am the customer'], $this->tasks(['related' => 'customer']));
        $this->assertSame(['I am the customer', 'Sooner', 'Later', 'No deadline'], $this->tasks(['related' => 'either']));
        $this->assertSame(['I am the customer', 'Not mine', 'Sooner', 'Later', 'No deadline'], $this->tasks(['related' => 'any']));
        $this->assertSame(['Done'], $this->tasks(['statuses' => ['3']]));
    }

    public function test_phone_calls_show_missed_and_todays_calls_and_as_far_ahead_as_asked(): void
    {
        $this->phoneCall('Missed', '2026-09-23 15:00:00');
        $this->phoneCall('Today', '2026-09-24 16:00:00');
        $this->phoneCall('Tomorrow', '2026-09-25 10:00:00');
        $this->phoneCall('Next week', '2026-10-01 10:00:00');
        $this->phoneCall('On hold', '2026-09-24 11:00:00', status: RecordStatus::OnHold);
        $this->phoneCall('Made', '2026-09-24 08:00:00', status: RecordStatus::Closed);
        $this->phoneCall('Colleague', '2026-09-24 12:00:00', employee: $this->colleague);

        $this->assertSame(['Missed', 'Today'], $this->calls([]));
        $this->assertSame(['Missed', 'Today', 'Tomorrow'], $this->calls(['future' => '1']));
        $this->assertSame(['Today', 'Tomorrow', 'Next week'], $this->calls(['past' => false, 'future' => '-1']));
        $this->assertSame(['Missed', 'Tomorrow'], $this->calls(['today' => false, 'future' => '2']));
    }

    public function test_phone_calls_show_the_description_on_hover_and_the_time_under_the_number(): void
    {
        $described = $this->phoneCall('Quote', '2026-09-23 15:00:00');
        $described->update(['description' => "Ask about the <b>quote</b>\nand the invoice"]);
        $bare = $this->phoneCall('Bare', '2026-09-24 16:00:00');

        $tooltipContains = fn (string $expected): Closure => fn (TextColumn $column): bool => str_contains($column->getTooltip()?->toHtml() ?? '', $expected);
        $time = fn (string $expected): Closure => fn (TextColumn $column): bool => (string) $column->getDescriptionBelow() === $expected;
        $describedTooltip = "Ask about the &lt;b&gt;quote&lt;/b&gt;<br />\nand the invoice";

        Livewire::test(PhoneCallsWidget::class)
            ->assertTableColumnDoesNotExist('called_at')
            ->assertTableColumnExists('customer', $tooltipContains('>Phone Call</span>'), $described)
            ->assertTableColumnExists('customer', $tooltipContains('<strong>Quote</strong>'), $described)
            ->assertTableColumnExists('customer', $tooltipContains($describedTooltip), $described)
            ->assertTableColumnExists('customer', $tooltipContains('Date and Time:</strong> 2026-09-23 15:00'), $described)
            ->assertTableColumnExists('customer', $tooltipContains('<strong>Bare</strong>'), $bare)
            ->assertTableColumnExists('phone_number', $time('2026-09-23 15:00'), $described)
            ->assertTableColumnExists('phone_number', $time('2026-09-24 16:00'), $bare);
    }

    public function test_tasks_show_the_description_on_hover_and_the_deadline_after_the_status(): void
    {
        $described = $this->task('Report', '2026-09-25 10:00:00', employee: $this->me);
        $described->update(['description' => 'Quarterly numbers']);
        $described->customers()->attach($this->colleague);
        $timeless = $this->task('Someday', '2026-09-26 00:00:00', employee: $this->me);
        $timeless->update(['timeless' => true]);
        $bare = $this->task('No deadline', null, employee: $this->me);

        $tooltipContains = fn (string $expected): Closure => fn (TextColumn $column): bool => str_contains($column->getTooltip()?->toHtml() ?? '', $expected);
        $prefix = fn (?string $expected): Closure => fn (TextColumn $column): bool => $column->getPrefix() === $expected;

        Livewire::test(TasksWidget::class)
            ->assertTableColumnExists('title', $tooltipContains('>Task</span>'), $described)
            ->assertTableColumnExists('title', $tooltipContains('<strong>Report</strong>'), $described)
            ->assertTableColumnExists('title', $tooltipContains('Quarterly numbers'), $described)
            ->assertTableColumnExists('title', $tooltipContains('Deadline:</strong> 2026-09-25 10:00'), $described)
            ->assertTableColumnExists('title', $tooltipContains('<strong>Customers:</strong> Col League'), $described)
            ->assertTableColumnExists('title', $tooltipContains('<strong>Someday</strong>'), $timeless)
            ->assertTableColumnExists('title', $tooltipContains('<strong>No deadline</strong>'), $bare)
            // Status and the deadline sit under the title, not in columns of their own.
            ->assertTableColumnStateSet('deadline', '2026-09-25 10:00', $described)
            ->assertTableColumnStateSet('deadline', '2026-09-26', $timeless)
            ->assertTableColumnExists('deadline', $prefix('Deadline: '), $described)
            ->assertTableColumnExists('deadline', $prefix('Deadline: '), $timeless)
            ->assertTableColumnExists('deadline', $prefix(null), $bare)
            ->assertDontSeeHtml('fi-ta-header-cell');
    }

    public function test_the_agenda_shows_my_open_events_for_the_chosen_days(): void
    {
        $meeting = Meeting::create(['title' => 'Review', 'date' => '2026-09-24', 'time' => '14:00:00', 'duration_minutes' => 60]);
        $meeting->employees()->attach($this->me);
        $far = Meeting::create(['title' => 'Far off', 'date' => '2026-10-04', 'time' => '10:00:00', 'duration_minutes' => 60]);
        $far->employees()->attach($this->me);
        $report = $this->task('Report', '2026-09-25 10:00:00', employee: $this->me);
        $this->task('Closed task', '2026-09-25 11:00:00', employee: $this->me, status: RecordStatus::Closed);
        $this->task("Colleague's", '2026-09-25 12:00:00', employee: $this->colleague);
        $call = $this->phoneCall('Call back', '2026-09-26 10:00:00');

        $this->assertSame(
            ['2026-09-24' => ['Review'], '2026-09-25' => ['Report'], '2026-09-26' => ['Call back']],
            $this->agenda([]),
        );
        $this->assertSame(['2026-09-24' => ['Review']], $this->agenda(['days' => '1']));
        $this->assertSame(['Review', 'Report', 'Call back', 'Far off'], array_merge(...array_values($this->agenda(['days' => '14']))));
        $this->assertSame(['2026-09-24' => ['Review'], '2026-09-26' => ['Call back']], $this->agenda(['types' => ['meeting', 'phone_call']]));

        $widget = Livewire::test(AgendaWidget::class)
            ->assertSee('Review')
            ->assertSee('Report')
            ->assertSeeHtml('epesi-agenda-type-icon')
            ->assertDontSeeHtml('background: var(--gray-400)');

        $calendarUrl = $widget->instance()->calendarUrl();
        parse_str((string) parse_url($calendarUrl, PHP_URL_QUERY), $query);
        $this->assertSame('listWeek', $query['view'] ?? null);

        $icons = $widget->instance()->events()->flatten(1)
            ->mapWithKeys(fn (array $row): array => [$row['type'] => $row['icon']]);

        $this->assertSame(Heroicon::OutlinedCalendarDays, $icons['Meeting']);
        $this->assertSame(Heroicon::OutlinedCheckCircle, $icons['Task']);
        $this->assertSame(Heroicon::OutlinedPhone, $icons['Phone Call']);

        $calendarEvents = Livewire::test(CalendarPage::class)->instance()->getEvents('2026-09-24', '2026-10-01');
        $iconsByEvent = collect($calendarEvents)->mapWithKeys(fn (array $event): array => [$event['id'] => $event['typeIconHtml']]);

        foreach ([
            'meeting-'.$meeting->id,
            'task-'.$report->id,
            'phone_call-'.$call->id,
        ] as $eventId) {
            $this->assertStringContainsString('<svg', $iconsByEvent[$eventId]);
        }
    }

    public function test_the_calendar_shows_only_my_records(): void
    {
        $mine = $this->task('Mine', '2026-09-25 10:00:00', employee: $this->me);
        $theirs = $this->task("Colleague's", '2026-09-25 12:00:00', employee: $this->colleague);

        $ids = collect(Livewire::test(CalendarPage::class)->instance()->getEvents('2026-09-24', '2026-10-01'))->pluck('id');

        $this->assertTrue($ids->contains('task-'.$mine->id));
        $this->assertFalse($ids->contains('task-'.$theirs->id));
    }

    public function test_the_agenda_shows_the_description_on_hover(): void
    {
        $meeting = Meeting::create(['title' => 'Review', 'description' => 'Bring the <b>slides</b>', 'date' => '2026-09-24', 'time' => '14:00:00', 'duration_minutes' => 60]);
        $meeting->employees()->attach($this->me);
        $meeting->customers()->attach($this->colleague);
        $company = Company::create(['company_name' => 'Alpine Field Logistics']);
        $meeting->customerCompanies()->attach($company);
        $task = $this->task('Report', '2026-09-25 00:00:00', employee: $this->me);
        $task->customers()->attach($this->colleague);
        $task->update(['timeless' => true]);
        $call = $this->phoneCall('Call back', '2026-09-26 10:30:00');

        $widget = Livewire::test(AgendaWidget::class);
        $tooltips = $widget->instance()->events()->flatten(1)
            ->mapWithKeys(fn (array $row): array => [$row['event']->id => $row['tooltip']->toHtml()]);

        $this->assertStringContainsString('>Meeting</span>', $tooltips['meeting-'.$meeting->id]);
        $this->assertStringContainsString('font-size: 0.8125rem', $tooltips['meeting-'.$meeting->id]);
        $this->assertStringContainsString('flex-wrap: nowrap', $tooltips['meeting-'.$meeting->id]);
        $this->assertLessThan(
            strpos($tooltips['meeting-'.$meeting->id], '>Meeting</span>'),
            strpos($tooltips['meeting-'.$meeting->id], '<svg'),
        );
        $this->assertStringContainsString('<strong>Review</strong>', $tooltips['meeting-'.$meeting->id]);
        $this->assertStringNotContainsString('Type:', $tooltips['meeting-'.$meeting->id]);
        $this->assertStringNotContainsString('Title:', $tooltips['meeting-'.$meeting->id]);
        $this->assertStringContainsString('Bring the &lt;b&gt;slides&lt;/b&gt;', $tooltips['meeting-'.$meeting->id]);
        $this->assertStringNotContainsString('Description:', $tooltips['meeting-'.$meeting->id]);
        $this->assertStringContainsString('Date and Time:</strong> 2026-09-24 14:00', $tooltips['meeting-'.$meeting->id]);
        $this->assertStringContainsString('<strong>Customers:</strong> Col League, Alpine Field Logistics', $tooltips['meeting-'.$meeting->id]);
        $this->assertStringContainsString('<strong>Deadline:</strong> 2026-09-25', $tooltips['task-'.$task->id]);
        $this->assertStringContainsString('<strong>Report</strong>', $tooltips['task-'.$task->id]);
        $this->assertStringContainsString('<div>-</div>', $tooltips['task-'.$task->id]);
        $this->assertStringContainsString('<strong>Customers:</strong> Col League', $tooltips['task-'.$task->id]);
        $this->assertStringContainsString('>Phone Call</span>', $tooltips['phone_call-'.$call->id]);
        $this->assertStringContainsString('<strong>Customers:</strong> Someone', $tooltips['phone_call-'.$call->id]);
        $widget->assertSeeHtml(Js::from(new HtmlString($tooltips['meeting-'.$meeting->id]))->toHtml());

        $calendarTooltips = collect(Livewire::test(CalendarPage::class)->instance()->getEvents('2026-09-24', '2026-10-01'))
            ->mapWithKeys(fn (array $event): array => [$event['id'] => $event['tooltip']]);
        foreach ($tooltips as $eventId => $tooltip) {
            $this->assertSame($tooltip, $calendarTooltips[$eventId]);
        }
    }

    public function test_the_agendas_plus_picks_a_type_and_opens_its_create_page_at_the_next_hour(): void
    {
        Livewire::test(AgendaWidget::class)
            ->callAction('createEvent', ['type' => 'task'])
            ->assertRedirect(TaskResource::getUrl('create', [
                'deadline' => Carbon::parse('2026-09-24 10:00:00')->toIso8601String(),
                'timeless' => 0,
            ]));
    }

    private function task(string $title, ?string $deadline, Contact $employee, ?Contact $customer = null, RecordStatus $status = RecordStatus::Open): Task
    {
        $task = Task::create(['title' => $title, 'deadline' => $deadline, 'status' => $status]);
        $task->employees()->attach($employee);

        if ($customer) {
            $task->customers()->attach($customer);
        }

        return $task;
    }

    private function phoneCall(string $subject, string $at, ?Contact $employee = null, RecordStatus $status = RecordStatus::Open): PhoneCall
    {
        $call = PhoneCall::create([
            'subject' => $subject,
            'called_at' => $at,
            'status' => $status,
            'other_customer' => true,
            'other_customer_name' => 'Someone',
        ]);
        $call->employees()->attach($employee ?? $this->me);

        return $call;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function tasks(array $settings): array
    {
        return Livewire::test(TasksWidget::class, ['appletSettings' => $settings])
            ->instance()->tasks()->pluck('title')->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function calls(array $settings): array
    {
        return Livewire::test(PhoneCallsWidget::class, ['appletSettings' => $settings])
            ->instance()->calls()->pluck('subject')->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, list<string>>
     */
    private function agenda(array $settings): array
    {
        return Livewire::test(AgendaWidget::class, ['appletSettings' => $settings])
            ->instance()->events()
            ->map(fn ($rows): array => $rows->map(fn (array $row): string => $row['event']->title)->values()->all())
            ->all();
    }
}
