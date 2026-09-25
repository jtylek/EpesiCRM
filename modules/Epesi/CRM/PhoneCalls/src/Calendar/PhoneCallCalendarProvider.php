<?php

namespace Epesi\Modules\CRM\PhoneCalls\Calendar;

use App\Models\User;
use App\Support\Calendar\CalendarColor;
use App\Support\Calendar\CalendarEvent;
use App\Support\Calendar\CalendarEventProvider;
use Carbon\Carbon;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\PhoneCallResource;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Illuminate\Support\Collection;

class PhoneCallCalendarProvider implements CalendarEventProvider
{
    public static function calendarKey(): string
    {
        return 'phone_call';
    }

    public static function calendarEvents(Carbon $start, Carbon $end, User $user): Collection
    {
        return PhoneCall::query()
            ->whereNotNull('called_at')
            ->whereBetween('called_at', [$start, $end])
            ->get()
            ->map(fn (PhoneCall $phoneCall): CalendarEvent => new CalendarEvent(
                id: self::calendarKey().'-'.$phoneCall->id,
                title: $phoneCall->subject,
                start: $phoneCall->called_at,
                end: null,
                allDay: false,
                url: PhoneCallResource::getUrl('view', ['record' => $phoneCall]),
                color: CalendarColor::css($phoneCall->status->getColor()),
                durationEditable: false,
            ))
            ->values();
    }

    public static function calendarLabel(): string
    {
        return 'Phone Call';
    }

    public static function calendarCreateUrl(Carbon $date, bool $allDay): string
    {
        return PhoneCallResource::getUrl('create', [
            'called_at' => $date->toIso8601String(),
        ]);
    }

    public static function calendarReschedule(string $recordId, Carbon $start, ?Carbon $end, bool $allDay, User $user): bool
    {
        $phoneCall = PhoneCall::query()->find($recordId);

        if (! $phoneCall || ! $user->can('update', $phoneCall)) {
            return false;
        }

        // PhoneCall has no all-day concept either — preserve the original
        // time-of-day rather than zeroing it if dropped on an all-day slot.
        if ($allDay) {
            $start = $start->clone()->setTimeFrom($phoneCall->called_at);
        }

        $phoneCall->called_at = $start;
        $phoneCall->save();

        return true;
    }
}
