<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\User;
use App\Support\Calendar\CalendarRegistry;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Merges every registered App\Support\Calendar\CalendarEventProvider's events
 * into one FullCalendar feed.
 */
class Calendar extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected string $view = 'filament.pages.calendar';

    public function getTitle(): string
    {
        return __('Calendar');
    }

    public static function getNavigationLabel(): string
    {
        return __('Calendar');
    }

    /**
     * Called directly from resources/js/calendar.js via $wire.getEvents(...)
     * as FullCalendar's event source — no separate JSON controller endpoint
     * needed, unlike Epesi's CRM_CalendarCommon::fullcalendar_events().
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEvents(string $start, string $end): array
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        /** @var User $user */
        $user = Auth::user();

        return collect(CalendarRegistry::all())
            ->flatMap(fn (string $provider) => $provider::calendarEvents($start, $end, $user))
            ->map(fn ($event) => $event->toArray())
            ->values()
            ->all();
    }

    /**
     * Called from resources/js/calendar.js's eventDrop/eventResize handlers.
     * Returns false on "not found"/"not authorized" so the JS side can snap
     * the event back to where it was — see each provider's
     * calendarReschedule() for the per-model semantics.
     */
    public function rescheduleEvent(string $id, string $start, ?string $end, bool $allDay): bool
    {
        [$key, $recordId] = explode('-', $id, 2);

        $provider = CalendarRegistry::find($key);

        if (! $provider) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();

        return $provider::calendarReschedule(
            $recordId,
            Carbon::parse($start),
            $end ? Carbon::parse($end) : null,
            $allDay,
            $user,
        );
    }

    /**
     * "New event" picker opened from an empty-cell click
     * (resources/js/calendar.js's dateClick handler calls
     * $wire.mountAction('createEvent', {date, allDay})) — the Filament
     * equivalent of Epesi's Leightbox "New Event" type picker
     * (CRM_Calendar::body()).
     */
    public function createEventAction(): Action
    {
        return Action::make('createEvent')
            ->label('New event')
            ->modalHeading(__('New event'))
            ->modalSubmitActionLabel(__('Continue'))
            ->schema([
                Radio::make('type')
                    ->label('Type')
                    ->options(fn (): array => collect(CalendarRegistry::all())
                        ->mapWithKeys(fn (string $provider): array => [$provider::calendarKey() => $provider::calendarLabel()])
                        ->all())
                    ->required(),
            ])
            ->action(function (array $data, array $arguments, Action $action): void {
                $provider = CalendarRegistry::find($data['type']);

                if (! $provider) {
                    return;
                }

                $date = Carbon::parse($arguments['date']);

                $action->redirect($provider::calendarCreateUrl($date, (bool) ($arguments['allDay'] ?? false)));
            });
    }
}
