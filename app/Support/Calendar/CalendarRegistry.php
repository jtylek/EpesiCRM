<?php

namespace App\Support\Calendar;

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
}
