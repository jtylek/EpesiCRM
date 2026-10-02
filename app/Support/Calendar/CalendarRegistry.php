<?php

namespace App\Support\Calendar;

use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * The list of CalendarEventProvider classes the Calendar page merges events
 * from — the Laravel-idiomatic equivalent of Epesi's per-module
 * CRM_CalendarCommon::new_event_handler() install-time call.
 *
 * Every provider is contributed by a module from its own service provider's
 * boot() (see the Meetings, Tasks and Phone Calls modules); nothing core-side
 * registers one. A new module adds events to the Calendar page by calling
 * register() the same way, and the page itself needs no change.
 */
class CalendarRegistry
{
    /** @var array<class-string<CalendarEventProvider>> */
    protected static array $providers = [];

    /**
     * @param  class-string<CalendarEventProvider>  $providerClass
     */
    public static function register(string $providerClass): void
    {
        if (! in_array($providerClass, static::$providers, true)) {
            static::$providers[] = $providerClass;
        }
    }

    /**
     * A provider's events between two moments on the signed-in user's wall
     * clock, each shifted to that clock too. Providers query stored UTC, so
     * the window is widened a day either way to catch what the offset moves
     * across its edge, then trimmed back after the shift.
     *
     * @param  class-string<CalendarEventProvider>  $provider
     * @return Collection<int, CalendarEvent>
     */
    public static function eventsBetween(string $provider, Carbon $start, Carbon $end, User $user, bool $mine = false): Collection
    {
        return $provider::calendarEvents(
            RegionalSetting::fromUser($start)->subDay(),
            RegionalSetting::fromUser($end)->addDay(),
            $user,
            $mine,
        )
            ->map(fn (CalendarEvent $event): CalendarEvent => $event->inUserTimezone())
            ->filter(fn (CalendarEvent $event): bool => $event->start->greaterThanOrEqualTo($start) && $event->start->lessThan($end))
            ->values();
    }

    /**
     * @return array<class-string<CalendarEventProvider>>
     */
    public static function all(): array
    {
        return static::$providers;
    }

    /**
     * @return class-string<CalendarEventProvider>|null
     */
    public static function find(string $key): ?string
    {
        foreach (static::$providers as $provider) {
            if ($provider::calendarKey() === $key) {
                return $provider;
            }
        }

        return null;
    }

    /** The navigation icon of the recordset whose morph alias backs a provider. */
    public static function typeIcon(string $provider): string|BackedEnum|Htmlable|null
    {
        $model = Relation::getMorphedModel($provider::calendarKey());
        $resource = $model ? Filament::getPanel('main', isStrict: false)?->getModelResource($model) : null;

        return $resource ? $resource::getNavigationIcon() : null;
    }
}
