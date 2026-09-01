<?php

namespace Epesi\Console\Demo;

/**
 * Shared "normal working day" window for the demo:generate:* commands.
 *
 * Faker's dateTimeBetween() gives these commands a useful *date* (+/-30 days)
 * but a time-of-day drawn uniformly across 24h, which produced demo data full
 * of 03:47 meetings, phone calls and deadlines. Each command keeps Faker's
 * date and replaces its clock time with a slot from this window instead.
 *
 * Static properties rather than constants deliberately: constants in traits
 * need PHP 8.2, and this app supports 8.1+ (README.md, compatibility_check.php).
 */
trait BusinessHours
{
    /** Start of the window, seconds from midnight. */
    protected static $day_start = 9 * 3600;   // 09:00
    /** End of the window. Records END by here - for anything with a duration
     *  this is not the latest permitted start. */
    protected static $day_end = 20 * 3600;    // 20:00
    /** Times snap to this, so demo data reads 14:30 rather than 14:37:52. */
    protected static $day_slot = 900;         // 15 minutes

    /**
     * A random start time inside the window, as seconds from midnight.
     *
     * Pass the record's duration when it has one, and the whole thing is kept
     * inside the window - a 3h meeting then starts no later than 17:00 for a
     * 20:00 day_end. Leave it 0 for point-in-time records (a phone call, a
     * deadline), which may land anywhere up to day_end itself.
     *
     * @param \Faker\Generator $faker
     * @param int $duration seconds the record occupies, 0 if it is a moment
     * @return int seconds from midnight, always on a $day_slot boundary
     */
    protected function business_hours_start($faker, $duration = 0)
    {
        $latest = max(self::$day_start, self::$day_end - $duration);
        $slots = intdiv($latest - self::$day_start, self::$day_slot);
        return self::$day_start + self::$day_slot * $faker->numberBetween(0, $slots);
    }
}
