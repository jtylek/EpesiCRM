<?php

namespace App\Services\LegacyImport;

use Carbon\Carbon;

class ActivityTimes
{
    /** Round current activity values, not historical edit timestamps or date-only deadlines. */
    public static function rounded(string $table, array $attributes): array
    {
        if ($table === 'meetings' && filled($attributes['date'] ?? null) && filled($attributes['time'] ?? null)) {
            $date = Carbon::parse($attributes['date'])->toDateString();
            $start = Carbon::parse($date.' '.$attributes['time'])->roundMinutes(5);

            return array_replace($attributes, [
                'date' => $start->toDateString() === $date ? $attributes['date'] : $start->toDateString(),
                'time' => $start->format('H:i:s'),
            ]);
        }

        $column = match ($table) {
            'phone_calls' => 'called_at',
            'tasks' => empty($attributes['timeless']) ? 'deadline' : null,
            default => null,
        };

        if ($column !== null && filled($attributes[$column] ?? null)) {
            $attributes[$column] = Carbon::parse($attributes[$column])->roundMinutes(5)->toDateTimeString();
        }

        return $attributes;
    }
}
