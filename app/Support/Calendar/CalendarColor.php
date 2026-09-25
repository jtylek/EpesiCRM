<?php

namespace App\Support\Calendar;

/**
 * Resolves one of Filament's own color names (the same tokens RecordStatus/
 * RecordPriority::getColor() already return for table badges — see
 * app/Enums/RecordStatus.php) to the CSS value FullCalendar wants, instead of
 * inventing a second color scheme for the calendar.
 */
final class CalendarColor
{
    public static function css(string $filamentColorName): string
    {
        return "var(--{$filamentColorName}-500)";
    }
}
