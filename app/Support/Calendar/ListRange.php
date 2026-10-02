<?php

namespace App\Support\Calendar;

use App\Support\UiState;

/**
 * How much time the Calendar's List view covers (User Settings → Calendar).
 * Kept with the user's other screen state, like App\Support\Calendar\WorkingHours.
 */
class ListRange
{
    public const DEFAULT = '7d';

    /** @return array<string, string> duration key => label */
    public static function options(): array
    {
        return [
            '1d' => __('1 day'),
            '3d' => __('3 days'),
            '7d' => __('7 days'),
            '2w' => __('2 weeks'),
            '1m' => __('1 month'),
        ];
    }

    public static function get(): string
    {
        $saved = UiState::recall('calendar.list_range', self::DEFAULT);

        return is_string($saved) && array_key_exists($saved, self::options()) ? $saved : self::DEFAULT;
    }

    public static function set(string $range): void
    {
        UiState::remember('calendar.list_range', $range);
    }
}
