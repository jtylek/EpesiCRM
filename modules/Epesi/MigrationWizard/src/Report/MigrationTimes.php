<?php

namespace Epesi\Modules\MigrationWizard\Report;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Dates and durations as the migration page and the report show them.
 */
final class MigrationTimes
{
    /** An ISO timestamp as the server's local date and time, null when there is none. */
    public static function local(?string $iso): ?string
    {
        if ($iso === null || $iso === '') {
            return null;
        }
        try {
            return Carbon::parse($iso)->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    /** "1 h 12 min 5 s" between two ISO timestamps, null unless both are known. */
    public static function elapsed(?string $from, ?string $to): ?string
    {
        if ($from === null || $to === null) {
            return null;
        }
        try {
            $seconds = max(0, Carbon::parse($to)->getTimestamp() - Carbon::parse($from)->getTimestamp());
        } catch (Throwable) {
            return null;
        }
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return ($hours > 0 ? "{$hours} h " : '').($hours > 0 || $minutes > 0 ? "{$minutes} min " : '').($seconds % 60).' s';
    }
}
