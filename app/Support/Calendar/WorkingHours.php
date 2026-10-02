<?php

namespace App\Support\Calendar;

use App\Support\UiState;

/**
 * The hours the Day and Week calendar views show in full; the hours before and
 * after collapse into a single row with an event count (resources/js/calendar.js).
 * Kept with the user's other screen state (App\Support\UiState), so it lives in
 * the session and survives a logout.
 */
class WorkingHours
{
    public const DEFAULT_START = 8;

    public const DEFAULT_END = 16;

    /** @return array{start: int, end: int} whole hours, 0–23 and 1–24 */
    public static function get(): array
    {
        $saved = (array) UiState::recall('calendar.working_hours', []);
        $start = (int) ($saved['start'] ?? self::DEFAULT_START);
        $end = (int) ($saved['end'] ?? self::DEFAULT_END);

        if ($start < 0 || $start > 23 || $end < 1 || $end > 24 || $end <= $start) {
            return ['start' => self::DEFAULT_START, 'end' => self::DEFAULT_END];
        }

        return ['start' => $start, 'end' => $end];
    }

    public static function set(int $start, int $end): void
    {
        UiState::remember('calendar.working_hours', ['start' => $start, 'end' => $end]);
    }

    /** @return array{morning: bool, evening: bool} which collapsed parts the user has expanded */
    public static function expanded(): array
    {
        $saved = (array) UiState::recall('calendar.expanded', []);

        return ['morning' => (bool) ($saved['morning'] ?? false), 'evening' => (bool) ($saved['evening'] ?? false)];
    }

    public static function setExpanded(bool $morning, bool $evening): void
    {
        UiState::remember('calendar.expanded', ['morning' => $morning, 'evening' => $evening]);
    }
}
