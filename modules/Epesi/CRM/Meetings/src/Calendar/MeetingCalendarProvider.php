<?php

namespace Epesi\Modules\CRM\Meetings\Calendar;

use App\Models\User;
use App\Support\Calendar\CalendarColor;
use App\Support\Calendar\CalendarEvent;
use App\Support\Calendar\CalendarEventProvider;
use Carbon\Carbon;
use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\MeetingResource;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Illuminate\Support\Collection;

class MeetingCalendarProvider implements CalendarEventProvider
{
    public static function calendarKey(): string
    {
        return 'meeting';
    }

    public static function calendarEvents(Carbon $start, Carbon $end, User $user): Collection
    {
        return Meeting::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->filter(fn (Meeting $meeting): bool => $meeting->starts_at !== null)
            ->map(fn (Meeting $meeting): CalendarEvent => new CalendarEvent(
                id: self::calendarKey().'-'.$meeting->id,
                title: $meeting->title,
                start: $meeting->starts_at,
                end: $meeting->duration_minutes
                    ? $meeting->starts_at->clone()->addMinutes($meeting->duration_minutes)
                    : null,
                allDay: false,
                url: MeetingResource::getUrl('view', ['record' => $meeting]),
                color: CalendarColor::css($meeting->status->getColor()),
            ))
            ->values();
    }

    public static function calendarLabel(): string
    {
        return 'Meeting';
    }

    public static function calendarCreateUrl(Carbon $date, bool $allDay): string
    {
        return MeetingResource::getUrl('create', [
            'date' => $date->toDateString(),
            'time' => $allDay ? '09:00' : $date->format('H:i'),
        ]);
    }

    public static function calendarReschedule(string $recordId, Carbon $start, ?Carbon $end, bool $allDay, User $user): bool
    {
        $meeting = Meeting::query()->find($recordId);

        if (! $meeting || ! $user->can('update', $meeting)) {
            return false;
        }

        $meeting->date = $start->toDateString();

        // Meetings don't have an all-day concept — a drop onto an all-day
        // row/cell carries no meaningful time, so keep whatever time the
        // meeting already had instead of zeroing it out.
        if (! $allDay) {
            $meeting->time = $start->format('H:i:s');
        }

        if ($end) {
            $meeting->duration_minutes = $start->diffInMinutes($end);
        }

        $meeting->save();

        return true;
    }
}
