<?php

namespace App\Support\Calendar;

use Carbon\Carbon;

/**
 * One calendar entry, already resolved to FullCalendar's plain event-object
 * shape by toArray() — see App\Filament\Pages\Calendar::getEvents().
 */
final readonly class CalendarEvent
{
    public function __construct(
        public string $id,
        public string $title,
        public Carbon $start,
        public ?Carbon $end,
        public bool $allDay,
        public string $url,
        public ?string $color = null,
        public ?bool $durationEditable = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            // Deliberately no UTC offset (not toIso8601String()) — these
            // dates are floating wall-clock values (Meeting's date/time have
            // no stored timezone concept at all), and the Calendar page
            // pairs this with FullCalendar's timeZone: 'UTC' option
            // (resources/js/calendar.js) so neither side ever converts
            // through the visiting browser's local timezone. Getting this
            // wrong silently shifts every drag-reschedule by the offset
            // between server and browser timezones.
            'start' => $this->start->format('Y-m-d\TH:i:s'),
            'end' => $this->end?->format('Y-m-d\TH:i:s'),
            'allDay' => $this->allDay,
            'url' => $this->url,
            'color' => $this->color,
            'durationEditable' => $this->durationEditable,
        ], fn (mixed $value): bool => $value !== null);
    }
}
