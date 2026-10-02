<?php

namespace App\Support\Calendar;

use Carbon\Carbon;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;

/**
 * One calendar entry, already resolved to FullCalendar's plain event-object
 * shape by toArray() — see App\Filament\Pages\Calendar::getEvents().
 */
final readonly class CalendarEvent
{
    /**
     * @param  list<string>  $customers
     */
    public function __construct(
        public string $id,
        public string $title,
        public Carbon $start,
        public ?Carbon $end,
        public bool $allDay,
        public string $url,
        public ?string $color = null,
        public ?bool $durationEditable = null,
        // Closed or canceled: the dashboard's Agenda leaves these out, as
        // Epesi's applet did.
        public bool $finished = false,
        // Shared tooltip details for the Agenda and Calendar page.
        public ?string $description = null,
        public array $customers = [],
        public bool $deadline = false,
    ) {}

    /**
     * The same event with its times shifted from the stored UTC instant to
     * the signed-in user's wall clock (floating, still labelled UTC — see
     * toArray()). An all-day event is a calendar day, so it is left alone.
     */
    public function inUserTimezone(): self
    {
        if ($this->allDay) {
            return $this;
        }

        $floating = fn (Carbon $moment): Carbon => Carbon::parse(RegionalSetting::toUser($moment)->format('Y-m-d H:i:s'), 'UTC');

        return new self(
            id: $this->id,
            title: $this->title,
            start: $floating($this->start),
            end: $this->end ? $floating($this->end) : null,
            allDay: $this->allDay,
            url: $this->url,
            color: $this->color,
            durationEditable: $this->durationEditable,
            finished: $this->finished,
            description: $this->description,
            customers: $this->customers,
            deadline: $this->deadline,
        );
    }

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
