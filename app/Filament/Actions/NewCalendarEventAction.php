<?php

namespace App\Filament\Actions;

use App\Support\Calendar\CalendarRegistry;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;

/**
 * "New event": pick a type (meeting, phone call, task, whatever the Calendar
 * providers offer), then go to that type's create page with the date filled
 * in. The Filament version of Epesi's Leightbox "New Event" type picker
 * (CRM_Calendar::body()).
 *
 * The Calendar page mounts it from an empty-cell click with the cell's
 * `date` and `allDay` (resources/js/calendar.js); the dashboard's Agenda
 * mounts it from its + with neither, which means the next full hour.
 */
class NewCalendarEventAction
{
    public static function make(string $name = 'createEvent'): Action
    {
        return Action::make($name)
            ->label('New event')
            ->modalHeading(__('New event'))
            ->modalSubmitActionLabel(__('Continue'))
            ->schema([
                Radio::make('type')
                    ->label('Type')
                    ->options(fn (): array => collect(CalendarRegistry::all())
                        ->mapWithKeys(fn (string $provider): array => [$provider::calendarKey() => __($provider::calendarLabel())])
                        ->all())
                    ->required(),
            ])
            ->action(function (array $data, array $arguments, Action $action): void {
                $provider = CalendarRegistry::find($data['type']);

                if (! $provider) {
                    return;
                }

                $date = isset($arguments['date'])
                    ? Carbon::parse($arguments['date'])
                    : now()->addHour()->startOfHour();

                $action->redirect($provider::calendarCreateUrl($date, (bool) ($arguments['allDay'] ?? false)));
            });
    }
}
