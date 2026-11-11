<?php

namespace Epesi\Modules\MigrationWizard\Import;

use Throwable;

/**
 * The warnings an importer wrote while it ran (an e-mail address already used, a link to a record that was not
 * imported, ...), kept one file per importer so the migration log can list them per module at the end. The state
 * keeps only their count. A display aid: never read back by the migration, and a failure to write is ignored.
 */
final class ImportWarnings
{
    public static function directory(): string
    {
        return storage_path('app/migration-wizard/warnings');
    }

    /** @param  list<string>  $warnings */
    public static function save(string $tab, array $warnings): void
    {
        try {
            if (! is_dir(self::directory())) {
                @mkdir(self::directory(), 0775, true);
            }
            @file_put_contents(self::file($tab), implode("\n", array_map(static fn (string $line): string => str_replace(["\r", "\n"], ' ', $line), $warnings)), LOCK_EX);
        } catch (Throwable) {
        }
    }

    /** @return list<string> */
    public static function read(string $tab): array
    {
        $text = is_file(self::file($tab)) ? (string) @file_get_contents(self::file($tab)) : '';

        return $text === '' ? [] : explode("\n", $text);
    }

    private static function file(string $tab): string
    {
        return self::directory().'/'.preg_replace('/[^a-z0-9_]/i', '_', $tab).'.txt';
    }
}
