<?php

namespace Epesi\Modules\MigrationWizard\Detection;

/**
 * Folders under the legacy data/ directory that hold only scratch files (caches, logs, Roundcube's temporary
 * uploads): nothing in them is migrated, so the scans neither count nor walk them. They stay where they are.
 */
final class ScratchAreas
{
    /** Relative to data/, with forward slashes. */
    public const PATHS = ['cache', 'logs', 'CRM_Roundcube/tmp'];

    /** Whether $path (a directory below $dataDir) is one of the scratch folders. */
    public static function skips(string $dataDir, string $path): bool
    {
        $base = rtrim(str_replace('\\', '/', $dataDir), '/').'/';
        $path = str_replace('\\', '/', $path);
        if (! str_starts_with($path, $base)) {
            return false;
        }

        return in_array(rtrim(substr($path, strlen($base)), '/'), self::PATHS, true);
    }
}
