<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\NewCalendarEventAction;
use App\Filament\Dashboard\Applet;
use App\Filament\Dashboard\AppletTooltip;
use App\Filament\Dashboard\IsApplet;
use App\Filament\Pages\Calendar;
use App\Models\User;
use App\Support\Calendar\CalendarEvent;
use App\Support\Calendar\CalendarEventProvider;
use App\Support\Calendar\CalendarRegistry;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * The Agenda applet (CRM_Calendar::applet()): the user's own events from
 * today on, from every Calendar provider they have ticked, closed ones left
 * out. Days are counted from today, so "1 week" is today and the six days
 * after it. Its + is the Calendar's "New event", as the Tasks and Phone
 * Calls applets have a + for a new task or call.
 */
class AgendaWidget extends Widget implements Applet, HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use IsApplet;

    protected string $view = 'filament.widgets.agenda';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 1;

    /** CRM_CalendarCommon::agenda_days_values(). */
    public const DAYS = [
        1 => '1 day',
        2 => '2 days',
        3 => '3 days',
        5 => '5 days',
        7 => '1 week',
        14 => '2 weeks',
        30 => '1 month',
        61 => '2 months',
    ];

    public static function canView(): bool
    {
        return Auth::check() && Calendar::canAccess() && CalendarRegistry::all() !== [];
    }

    public static function getAppletCaption(): string
    {
        return __('Agenda');
    }

    public static function getAppletDescription(): ?string
    {
        return __('Your meetings, tasks and phone calls for the coming days');
    }

    public static function getAppletSettingsDefaults(): array
    {
        return [
            'days' => 7,
            'types' => array_map(fn (string $provider): string => $provider::calendarKey(), CalendarRegistry::all()),
        ];
    }

    public static function getAppletSettingsSchema(): array
    {
        return [
            Select::make('days')
                ->label('Look for events in')
                ->options(array_map(fn (string $label): string => __($label), self::DAYS))
                ->required()
                ->selectablePlaceholder(false),
            CheckboxList::make('types')
                ->label('Show')
                ->options(collect(CalendarRegistry::all())
                    ->mapWithKeys(fn (string $provider): array => [$provider::calendarKey() => __($provider::calendarLabel())])
                    ->all()),
        ];
    }

    public function days(): int
    {
        $days = (int) $this->appletSetting('days');

        return array_key_exists($days, self::DAYS) ? $days : 7;
    }

    /**
     * The events by day (Y-m-d), each with the singular label of its type and
     * what it shows on hover: the title, cut short on the row, then the
     * description and the time, as the other applets have them.
     *
     * @return Collection<string, Collection<int, array{event: CalendarEvent, type: string, tooltip: ?HtmlString}>>
     */
    public function events(): Collection
    {
        /** @var User $user */
        $user = Auth::user();
        $start = today();
        $end = today()->addDays($this->days());
        $types = array_map('strval', (array) $this->appletSetting('types'));

        return collect(CalendarRegistry::all())
            ->filter(fn (string $provider): bool => in_array($provider::calendarKey(), $types, true))
            ->flatMap(fn (string $provider): Collection => $this->eventsOf($provider, $start, $end, $user))
            ->reject(fn (array $row): bool => $row['event']->finished || $row['event']->start->greaterThanOrEqualTo($end))
            ->sortBy(fn (array $row): string => $row['event']->start->format('Y-m-d H:i:s'))
            ->groupBy(fn (array $row): string => $row['event']->start->toDateString());
    }

    public function calendarUrl(): string
    {
        return Calendar::getUrl();
    }

    public function createEventAction(): Action
    {
        return NewCalendarEventAction::make()
            ->tooltip(__('New event'))
            ->icon(Heroicon::OutlinedPlus)
            ->iconButton()
            ->size(Size::Small)
            ->color('gray');
    }

    /**
     * @param  class-string<CalendarEventProvider>  $provider
     * @return Collection<int, array{event: CalendarEvent, type: string, tooltip: ?HtmlString}>
     */
    private function eventsOf(string $provider, Carbon $start, Carbon $end, User $user): Collection
    {
        $type = __($provider::calendarLabel());

        return $provider::calendarEvents($start, $end, $user, mine: true)
            ->map(fn (CalendarEvent $event): array => [
                'event' => $event,
                'type' => $type,
                'tooltip' => AppletTooltip::text($event->description),
            ]);
    }
}
