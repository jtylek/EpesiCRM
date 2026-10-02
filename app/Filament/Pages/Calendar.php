<?php

namespace App\Filament\Pages;

use App\Filament\Actions\NewCalendarEventAction;
use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\User;
use App\Support\Calendar\CalendarEventTooltip;
use App\Support\Calendar\CalendarRegistry;
use BackedEnum;
use Carbon\Carbon;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

use function Filament\Support\generate_icon_html;

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

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

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
            ->flatMap(function (string $provider) use ($start, $end, $user): array {
                $icon = generate_icon_html(CalendarRegistry::typeIcon($provider), size: IconSize::Small)?->toHtml();

                return CalendarRegistry::eventsBetween($provider, $start, $end, $user)
                    ->map(fn ($event): array => [
                        ...$event->toArray(),
                        'typeIconHtml' => $icon,
                        'tooltip' => CalendarEventTooltip::make($event, $provider)->toHtml(),
                    ])
                    ->all();
            })
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

        // FullCalendar hands back wall-clock times on the user's clock; the
        // providers store UTC. An all-day drop is a bare date and stays as is.
        $toStored = fn (string $moment): Carbon => $allDay
            ? Carbon::parse($moment)
            : RegionalSetting::fromUser(Carbon::parse($moment));

        return $provider::calendarReschedule(
            $recordId,
            $toStored($start),
            $end ? $toStored($end) : null,
            $allDay,
            $user,
        );
    }

    /**
     * "New event" picker opened from an empty-cell click
     * (resources/js/calendar.js's dateClick handler calls
     * $wire.mountAction('createEvent', {date, allDay})).
     */
    public function createEventAction(): Action
    {
        return NewCalendarEventAction::make();
    }
}
