<?php

namespace App\Filament\GlobalSearch;

use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;

/**
 * Filament's own provider, except that a single letter searches nothing: it
 * matches nearly every record of every resource, which is 35-odd queries per
 * keystroke for a list nobody can use.
 */
class MinimumLengthGlobalSearchProvider extends DefaultGlobalSearchProvider
{
    public const MINIMUM_LENGTH = 2;

    public function getResults(string $query): ?GlobalSearchResults
    {
        if (mb_strlen(trim($query)) < static::MINIMUM_LENGTH) {
            return null;
        }

        return parent::getResults($query);
    }
}
