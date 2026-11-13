<?php

namespace Epesi\Modules\ActivityReport;

use App\Models\LoginAudit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Epesi\Modules\RecordBrowser\Recordset\LinkableRecordsets;
use Filament\Support\Facades\FilamentTimezone;

class ActivitySummary
{
    public static function dates(string $period): array
    {
        $today = CarbonImmutable::now(FilamentTimezone::get());

        return match ($period) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'week' => [$today->startOfWeek()->toDateString(), $today->toDateString()],
            '7', '30', '90' => [$today->subDays((int) $period - 1)->toDateString(), $today->toDateString()],
            'year' => [$today->startOfYear()->toDateString(), $today->toDateString()],
            default => [$today->startOfMonth()->toDateString(), $today->toDateString()],
        };
    }

    public static function forUser(User $viewer, string $selectedUser, string $from, string $until): array
    {
        abort_unless(ActivityReport::canAccess($viewer), 403);
        $ids = ActivityReport::allowedUserIds($viewer);
        if ($selectedUser !== 'all' || ! ActivityReport::canPickUser($viewer)) {
            $selected = ActivityReport::canPickUser($viewer) ? (int) $selectedUser : $viewer->getKey();
            $ids = array_values(array_intersect($ids, [$selected]));
        }

        $timezone = FilamentTimezone::get();
        $start = CarbonImmutable::parse($from, $timezone)->startOfDay();
        $end = CarbonImmutable::parse($until, $timezone)->addDay()->startOfDay();
        $days = [];
        for ($day = $start; $day < $end; $day = $day->addDay()) {
            $days[$day->toDateString()] = ['logins' => 0, 'seconds' => 0, 'activities' => 0];
        }

        $query = ActivityReport::apply(ActivityReport::query($ids), [
            'from' => $from, 'until' => $until,
            'kinds' => [...ActivityReport::DEFAULT_KINDS, ActivityReport::FILES],
        ]);
        $groups = (clone $query)->selectRaw("subject_type, COUNT(*) as total,
            COUNT(DISTINCT CASE WHEN event IN ('created', 'cloned') THEN subject_id END) as created_count,
            COUNT(DISTINCT CASE WHEN event = 'updated' THEN subject_id END) as updated_count,
            COUNT(DISTINCT CASE WHEN event = 'deleted' THEN subject_id END) as deleted_count")
            ->groupBy('subject_type')->get();
        $modules = [];
        foreach ($groups as $group) {
            $alias = $group->subject_type;
            $modules[$alias] = [
                'label' => $alias === ActivityReport::attachmentAlias() ? __('Notes and files') : LinkableRecordsets::label($alias),
                'icon' => LinkableRecordsets::icon($alias) ?? 'heroicon-o-paper-clip',
                'total' => (int) $group->total,
                'created' => (int) $group->created_count,
                'updated' => (int) $group->updated_count,
                'deleted' => (int) $group->deleted_count,
            ];
        }
        uasort($modules, fn (array $a, array $b): int => $a['label'] <=> $b['label']);

        // Convert in PHP: database DATE() groups UTC days, not the viewer's days (including DST).
        foreach ((clone $query)->select(['id', 'created_at'])->lazyById(1000) as $activity) {
            $date = $activity->created_at->timezone($timezone)->toDateString();
            $days[$date]['activities']++;
        }

        $sessions = LoginAudit::query()->whereIn('user_id', $ids)
            ->where('started_at', '<', $end->utc())
            ->where(fn ($q) => $q->where('ended_at', '>=', $start->utc())->orWhere('started_at', '>=', $start->utc()))
            ->orderBy('started_at')->get(['user_id', 'started_at', 'ended_at']);
        $intervals = [];
        foreach ($sessions as $session) {
            $login = CarbonImmutable::instance($session->started_at);
            if ($login >= $start && $login < $end) {
                $days[$login->timezone($timezone)->toDateString()]['logins']++;
            }
            // Missing end times provide no evidence of duration; never extend them to now.
            $finish = CarbonImmutable::instance($session->ended_at ?? $session->started_at)->min($end)->min(CarbonImmutable::now());
            $begin = $login->max($start);
            if ($finish <= $begin) {
                continue;
            }
            $user = $session->user_id;
            $last = array_key_last($intervals[$user] ?? []);
            if ($last !== null && $intervals[$user][$last][1] >= $begin) {
                $intervals[$user][$last][1] = $intervals[$user][$last][1]->max($finish);
            } else {
                $intervals[$user][] = [$begin, $finish];
            }
        }
        foreach ($intervals as $userIntervals) {
            foreach ($userIntervals as [$begin, $finish]) {
                for ($cursor = $begin->timezone($timezone); $cursor < $finish; $cursor = $next) {
                    $next = $cursor->addDay()->startOfDay()->min($finish);
                    $days[$cursor->toDateString()]['seconds'] += $next->getTimestamp() - $cursor->getTimestamp();
                }
            }
        }

        return [
            'modules' => $modules, 'days' => array_reverse($days, true),
            'logins' => array_sum(array_column($days, 'logins')),
            'seconds' => array_sum(array_column($days, 'seconds')),
            'activities' => array_sum(array_column($days, 'activities')),
            'active_days' => count(array_filter($days, fn (array $day): bool => $day['activities'] > 0)),
        ];
    }

    public static function duration(int $seconds): string
    {
        return __(':hours h :minutes min', ['hours' => intdiv($seconds, 3600), 'minutes' => intdiv($seconds % 3600, 60)]);
    }

    /**
     * Lays the days out as a contribution calendar: one column per week (Monday first),
     * each day graded 0-4 by the quartiles of the active days' activity counts.
     *
     * @param  array<string, array{logins: int, seconds: int, activities: int}>  $days
     * @return array{weeks: list<list<array|null>>, months: list<array{column: int, span: int, label: string}>}
     */
    public static function calendar(array $days): array
    {
        ksort($days);
        if ($days === []) {
            return ['weeks' => [], 'months' => []];
        }

        $counts = array_values(array_filter(array_column($days, 'activities')));
        sort($counts);
        $quartile = fn (float $q): int => $counts[(int) floor((count($counts) - 1) * $q)] ?? 0;
        $thresholds = [$quartile(0.25), $quartile(0.5), $quartile(0.75)];

        $weeks = [];
        $months = [];
        $week = CarbonImmutable::parse(array_key_first($days))->startOfWeek(CarbonImmutable::MONDAY);
        $last = array_key_last($days);
        for ($column = 0; $week->toDateString() <= $last; $week = $week->addWeek(), $column++) {
            $cells = [];
            for ($offset = 0; $offset < 7; $offset++) {
                $date = $week->addDays($offset);
                $day = $days[$date->toDateString()] ?? null;
                if ($day === null) {
                    $cells[] = null;

                    continue;
                }
                $activities = $day['activities'];
                $level = $activities === 0 ? 0 : 1 + count(array_filter($thresholds, fn (int $t): bool => $activities > $t));
                $cells[] = $day + ['date' => $date->toDateString(), 'level' => $level];
                if ($months === [] || $date->day === 1) {
                    $months[] = ['column' => $column, 'label' => $date->translatedFormat('M')];
                }
            }
            $weeks[] = $cells;
        }

        // A label runs up to the next one; a month that starts in the first label's column replaces it.
        $labels = [];
        foreach ($months as $i => $month) {
            $span = ($months[$i + 1]['column'] ?? count($weeks)) - $month['column'];
            if ($span > 0) {
                $labels[] = $month + ['span' => $span];
            }
        }

        return ['weeks' => $weeks, 'months' => $labels];
    }
}
