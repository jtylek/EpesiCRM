<?php

namespace Epesi\Modules\MigrationWizard\Detection;

use App\Support\Setup\LegacyBootDetector;

/**
 * Whether a legacy Epesi installation sits in this folder (a readable data/config.php with its database settings).
 * The migration pages and their actions do nothing unless it does. A test can bind
 * `migration-wizard.legacy_detected` in the container to decide without touching the real folder.
 */
final class LegacyPresence
{
    public static function detected(): bool
    {
        if (app()->bound('migration-wizard.legacy_detected')) {
            return (bool) app('migration-wizard.legacy_detected');
        }

        return LegacyBootDetector::detect(base_path()) === 'legacy';
    }
}
