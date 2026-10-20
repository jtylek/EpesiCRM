<?php

namespace App\Filament\Concerns;

/**
 * Filament shows up to 50 hits per resource, and the box lists every resource
 * that has any: a handful of lines per group is what anyone reads, and each
 * group is a LIKE '%term%' query that stops sooner with a smaller LIMIT.
 */
trait LimitsGlobalSearchResults
{
    public static function getGlobalSearchResultsLimit(): int
    {
        return 10;
    }
}
