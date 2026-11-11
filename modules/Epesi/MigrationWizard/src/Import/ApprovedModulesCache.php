<?php

namespace Epesi\Modules\MigrationWizard\Import;

use Throwable;

/**
 * Which legacy modules the in-place import may run, worked out ONCE per migration. Working it out means a full
 * inventory of the installation (every table, and a walk over the whole data/ folder: about 12 seconds on a site
 * with 35,000 files), which a browser run repeated in every 20-second slice, leaving little time to import. It
 * cannot change during the import (the tables are already renamed), and the files the import moves out of data/
 * must not change the answer half-way either.
 *
 * Forgotten when Epesi 3 is installed into the database (LivePhases::install), so every run starts with a fresh
 * answer; keyed by the rename map, so a different installation never reuses it.
 */
final class ApprovedModulesCache
{
    public static function path(): string
    {
        return storage_path('app/migration-wizard/approved-modules.json');
    }

    /**
     * @param  array<string, string>  $renameMap
     * @param  callable(): list<string>  $compute
     * @return list<string>
     */
    public static function get(array $renameMap, callable $compute): array
    {
        $key = hash('sha256', (string) json_encode($renameMap));
        try {
            $cached = is_file(self::path()) ? json_decode((string) file_get_contents(self::path()), true) : null;
        } catch (Throwable) {
            $cached = null;
        }
        if (is_array($cached) && ($cached['key'] ?? null) === $key && is_array($cached['modules'] ?? null)) {
            return array_values(array_map('strval', $cached['modules']));
        }

        $modules = array_values($compute());
        try {
            @file_put_contents(self::path(), json_encode(['key' => $key, 'modules' => $modules]), LOCK_EX);
        } catch (Throwable) {
        }

        return $modules;
    }

    public static function forget(): void
    {
        @unlink(self::path());
    }
}
