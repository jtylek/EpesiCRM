<?php

namespace Epesi\Modules\CRM\Meetings\Calendar;

use App\Enums\RecordStatus;
use App\Models\User;
use App\Support\Calendar\CalendarColor;
use App\Support\Calendar\CalendarEvent;
use App\Support\Calendar\CalendarEventProvider;
use Carbon\Carbon;
use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\MeetingResource;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MeetingCalendarProvider implements CalendarEventProvider
{
    public static function calendarKey(): string
    {
        return 'meeting';
    }

    public static function calendarEvents(Carbon $start, Carbon $end, User $user, bool $mine = false): Collection
    {
        return Meeting::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with(['customers', 'customerCompanies'])
            ->when($mine, fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                ->whereHas('employees', fn (Builder $contacts): Builder => $contacts->where('user_id', $user->id))
                ->orWhereHas('customers', fn (Builder $contacts): Builder => $contacts->where('user_id', $user->id))))
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
                color: CalendarColor::forKind(CalendarColor::MEETING, in_array($meeting->status, RecordStatus::finished(), true)),
                finished: in_array($meeting->status, RecordStatus::finished(), true),
                description: $meeting->description,
                customers: $meeting->customers->pluck('full_name')
                    ->merge($meeting->customerCompanies->pluck('company_name'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ))
            ->values();
    }

    public static function calendarLabel(): string
    {
        return 'Meeting';
    }

    public static function calendarCreateUrl(Carbon $date, bool $allDay): string
    {
        // The clicked slot is on the user's clock; meetings are stored in UTC.
        $slot = RegionalSetting::fromUser($allDay ? $date->clone()->setTime(9, 0) : $date);

        return MeetingResource::getUrl('create', [
            'date' => $slot->toDateString(),
            'time' => $slot->format('H:i'),
        ]);
    }

    public static function calendarReschedule(string $recordId, Carbon $start, ?Carbon $end, bool $allDay, User $user): bool
    {
        $meeting = Meeting::query()->find($recordId);

        if (! $meeting || ! $user->can('update', $meeting)) {
            return false;
        }

        // Meetings don't have an all-day concept — a drop onto an all-day
        // row/cell carries no meaningful time, so keep whatever time the
        // meeting already had (on the user's clock) instead of zeroing it out.
        if ($allDay && $meeting->starts_at) {
            $start = RegionalSetting::fromUser($start->clone()->setTimeFrom(RegionalSetting::toUser($meeting->starts_at)));
        }

        $meeting->date = $start->toDateString();
        $meeting->time = $start->format('H:i:s');

        if ($end) {
            $meeting->duration_minutes = $start->diffInMinutes($end);
        }

        $meeting->save();

        return true;
    }
}
