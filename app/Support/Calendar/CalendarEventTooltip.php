<?php

namespace App\Support\Calendar;

use App\Filament\Dashboard\AppletTooltip;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Illuminate\Support\HtmlString;

final class CalendarEventTooltip
{
    /**
     * @param  class-string<CalendarEventProvider>  $provider
     */
    public static function make(CalendarEvent $event, string $provider): HtmlString
    {
        // The event is already on the user's wall clock (see CalendarRegistry::eventsBetween()).
        $date = $event->start->format($event->allDay ? RegionalSetting::dateFormat() : RegionalSetting::dateTimeFormat());

        return AppletTooltip::details(
            type: $provider::calendarLabel(),
            icon: CalendarRegistry::typeIcon($provider),
            title: $event->title,
            description: $event->description,
            dateLabel: $event->deadline ? 'Deadline' : 'Date and Time',
            date: $date,
            customers: $event->customers,
        );
    }
}
