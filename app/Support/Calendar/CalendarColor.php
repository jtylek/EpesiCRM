<?php

namespace App\Support\Calendar;

/**
 * The calendar colors its events by kind, not by status: meetings green, tasks
 * blue, phone calls orange, any other event dark grey unless it names its own
 * color, and a finished (closed/canceled) event light grey whatever its kind.
 */
final class CalendarColor
{
    public const MEETING = '#16a34a';

    public const TASK = '#2563eb';

    public const PHONE_CALL = '#f97316';

    public const OTHER = '#4b5563';

    public const FINISHED = '#d1d5db';

    public static function css(string $color): string
    {
        return str_starts_with($color, '#') ? $color : "var(--{$color}-500)";
    }

    /** The color of an event of one kind; $kind is one of the kind constants. */
    public static function forKind(string $kind, bool $finished = false): string
    {
        return self::css($finished ? self::FINISHED : $kind);
    }
}
