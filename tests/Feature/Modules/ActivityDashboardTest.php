<?php

namespace Tests\Feature\Modules;

use App\Models\LoginAudit;
use Epesi\Modules\ActivityReport\ActivitySummary;
use Epesi\Modules\ActivityReport\Filament\Pages\ActivityDashboard;
use Epesi\Modules\ActivityReport\Filament\Pages\UserActivityReport;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class ActivityDashboardTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_sessions_are_clipped_split_at_midnight_and_merged_per_user(): void
    {
        FilamentTimezone::set('UTC');
        $user = $this->userWithRole();
        foreach ([
            ['2025-01-01 23:00', '2025-01-02 01:00'],
            ['2025-01-02 00:30', '2025-01-02 02:00'],
            ['2025-01-02 23:00', '2025-01-03 02:00'],
            ['2025-01-02 12:00', '2025-01-02 12:00'],
        ] as [$start, $end]) {
            LoginAudit::create(['user_id' => $user->id, 'login' => $user->email, 'ip_address' => '127.0.0.1', 'started_at' => $start, 'ended_at' => $end]);
        }
        $summary = ActivitySummary::forUser($user, (string) $user->id, '2025-01-02', '2025-01-02');
        $this->assertSame(3, $summary['logins']);
        $this->assertSame(10800, $summary['seconds']);
        $this->assertSame(10800, $summary['days']['2025-01-02']['seconds']);
    }

    public function test_activity_counts_events_and_distinct_records_in_the_viewers_timezone(): void
    {
        FilamentTimezone::set('Europe/Budapest');
        $user = $this->userWithRole();
        foreach (['created', 'updated', 'updated', 'deleted'] as $event) {
            Activity::create([
                'causer_type' => 'user', 'causer_id' => $user->id,
                'subject_type' => 'task', 'subject_id' => 987,
                'description' => $event, 'event' => $event, 'created_at' => '2025-01-01 23:30:00',
            ]);
        }
        $summary = ActivitySummary::forUser($user, (string) $user->id, '2025-01-02', '2025-01-02');
        $this->assertSame(4, $summary['activities']);
        $this->assertSame(1, $summary['modules']['task']['created']);
        $this->assertSame(1, $summary['modules']['task']['updated']);
        $this->assertSame(4, $summary['days']['2025-01-02']['activities']);
        $this->assertSame(1, $summary['active_days']);
    }

    public function test_forged_user_selection_cannot_reveal_other_users_sessions(): void
    {
        $employee = $this->userWithRole();
        $other = $this->userWithRole();
        LoginAudit::create(['user_id' => $other->id, 'login' => $other->email, 'ip_address' => '127.0.0.1', 'started_at' => '2025-01-02 10:00', 'ended_at' => '2025-01-02 11:00']);
        foreach (['all', (string) $other->id] as $selected) {
            $summary = ActivitySummary::forUser($employee, $selected, '2025-01-01', '2025-01-03');
            $this->assertSame(0, $summary['logins']);
            $this->assertSame(0, $summary['seconds']);
        }
        $manager = $this->userWithRole('manager');
        $this->assertSame(1, ActivitySummary::forUser($manager, 'all', '2025-01-01', '2025-01-03')['logins']);
        $other->update(['active' => false]);
        $this->assertSame(0, ActivitySummary::forUser($manager, (string) $other->id, '2025-01-01', '2025-01-03')['logins']);
    }

    public function test_dashboard_renders_and_rejects_reversed_dates(): void
    {
        $this->actingAs($this->userWithRole('manager'));
        Livewire::test(ActivityDashboard::class)
            ->assertSee('Activity by module')
            ->assertSee('Daily activity')
            ->assertSee('Activity details')
            ->set('data.from', '2025-02-02')->set('data.until', '2025-02-01')
            ->call('applyFilters')->assertHasErrors(['data.until'])
            ->set('data.until', '2025-02-03')->call('applyFilters')->assertHasNoErrors();
        $this->assertStringEndsWith('/activity-report', ActivityDashboard::getUrl());
        $this->assertStringEndsWith('/activity-report/events', UserActivityReport::getUrl());
    }

    public function test_period_boundaries(): void
    {
        FilamentTimezone::set('UTC');
        $this->travelTo(now()->setDate(2026, 11, 11)->startOfDay());
        $this->assertSame(['2026-11-09', '2026-11-11'], ActivitySummary::dates('week'));
        $this->assertSame(['2026-11-01', '2026-11-11'], ActivitySummary::dates('month'));
        $this->assertSame(['2026-11-05', '2026-11-11'], ActivitySummary::dates('7'));
        $this->assertSame(['2026-10-13', '2026-11-11'], ActivitySummary::dates('30'));
        $this->assertSame(['2026-08-14', '2026-11-11'], ActivitySummary::dates('90'));
        $this->assertSame(['2026-01-01', '2026-11-11'], ActivitySummary::dates('year'));
    }

    public function test_badges_open_the_existing_report_with_the_selected_user_dates_module_and_action(): void
    {
        $manager = $this->userWithRole('manager');
        $employee = $this->userWithRole();
        $this->actingAs($manager);
        $rows = [];
        foreach (['created', 'updated'] as $event) {
            $rows[$event] = Activity::create([
                'causer_type' => 'user', 'causer_id' => $employee->id,
                'subject_type' => 'contact', 'subject_id' => 987,
                'description' => $event, 'event' => $event, 'created_at' => '2025-02-02 12:00:00',
            ]);
        }
        $dashboard = Livewire::test(ActivityDashboard::class)
            ->set('data.user', (string) $employee->id)
            ->set('data.from', '2025-02-01')->set('data.until', '2025-02-03')
            ->call('applyFilters')->assertHasNoErrors();

        foreach (['new' => 'created', 'edit' => 'updated'] as $kind => $event) {
            parse_str(parse_url($dashboard->instance()->detailsUrl('contact', $kind), PHP_URL_QUERY), $params);
            $this->assertSame((string) $employee->id, $params['filters']['user']['value']);
            $this->assertSame(['contact'], $params['filters']['report']['recordsets']);
            Livewire::withQueryParams($params)->test(UserActivityReport::class)
                ->assertCanSeeTableRecords([$rows[$event]])
                ->assertCanNotSeeTableRecords([$rows[$event === 'created' ? 'updated' : 'created']]);
        }
        parse_str(parse_url($dashboard->instance()->detailsUrl(date: '2025-02-02'), PHP_URL_QUERY), $params);
        $this->assertSame('2025-02-02', $params['filters']['report']['from']);
        $this->assertSame('2025-02-02', $params['filters']['report']['until']);
        Livewire::withQueryParams($params)->test(UserActivityReport::class)
            ->assertCanSeeTableRecords(array_values($rows));
        Livewire::withQueryParams([])->test(ActivityDashboard::class)
            ->assertSet('filters.from', '2025-02-01')
            ->assertSet('filters.user', (string) $employee->id);
    }

    public function test_calendar_lays_days_out_in_monday_first_weeks_graded_by_quartile(): void
    {
        $days = [];
        foreach ([
            '2025-01-01' => 0, '2025-01-02' => 1, '2025-01-03' => 2, '2025-01-04' => 3,
            '2025-01-05' => 4, '2025-01-06' => 50, '2025-01-07' => 0,
        ] as $date => $activities) {
            $days[$date] = ['logins' => 1, 'seconds' => 60, 'activities' => $activities];
        }

        $calendar = ActivitySummary::calendar(array_reverse($days, true));

        $this->assertCount(2, $calendar['weeks']);
        $this->assertNull($calendar['weeks'][0][0]);
        $this->assertNull($calendar['weeks'][0][1]);
        $this->assertSame('2025-01-01', $calendar['weeks'][0][2]['date']);
        $this->assertSame('2025-01-06', $calendar['weeks'][1][0]['date']);
        $this->assertNull($calendar['weeks'][1][2]);
        $this->assertSame(
            [0, 1, 1, 2, 3, 4, 0],
            array_map(fn (array $day): int => $day['level'], [...array_slice($calendar['weeks'][0], 2), ...array_slice($calendar['weeks'][1], 0, 2)]),
        );
        $this->assertSame([['column' => 0, 'label' => 'Jan', 'span' => 2]], $calendar['months']);
    }

    public function test_employee_dashboard_and_preset_selection_render(): void
    {
        $this->actingAs($this->userWithRole());
        Livewire::test(ActivityDashboard::class)->assertSee('Daily activity')
            ->assertSet('data.period', '30')
            ->assertSet('filters.from', ActivitySummary::dates('30')[0])
            ->set('data.period', 'year')->call('applyFilters')->assertHasNoErrors()
            ->assertSet('filters.from', ActivitySummary::dates('year')[0]);
    }

    public function test_session_duration_uses_elapsed_seconds_across_daylight_saving(): void
    {
        FilamentTimezone::set('Europe/Budapest');
        $user = $this->userWithRole();
        LoginAudit::create([
            'user_id' => $user->id, 'login' => $user->email, 'ip_address' => '127.0.0.1',
            'started_at' => '2025-03-29 23:00:00', 'ended_at' => '2025-03-30 22:00:00',
        ]);
        $summary = ActivitySummary::forUser($user, (string) $user->id, '2025-03-30', '2025-03-30');
        $this->assertSame(23 * 3600, $summary['seconds']);
        $this->assertSame(1, $summary['logins']);
    }
}
