<?php

namespace App\Services\LegacyImport;

/**
 * Per-tab counters and warnings for `php artisan import:legacy`'s console
 * output — kept as one small value object so every Importer reports the
 * same shape rather than each command handler formatting its own.
 */
class ImportSummary
{
    public int $created = 0;

    public int $updated = 0;

    public int $historyRows = 0;

    /** @var list<string> */
    public array $warnings = [];

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
