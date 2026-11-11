<?php

namespace Epesi\Modules\MigrationWizard\Database;

use Epesi\Modules\MigrationWizard\Verification\MigrationVerifier;
use PDO;
use RuntimeException;

/**
 * Drops the legacy tables Epesi 3 has no use for right after verification passed, without asking (user decision):
 * every table except Premium and Custom modules' (their data has no importer and stays until one exists). The
 * administrator's pre-upgrade backup holds them. Only namespaced tables are dropped (`legacy_<name>`, the exact rename
 * map's names). Files are never removed here: the junk in data/ goes through LegacyJunk.
 */
final class UnownedLegacyData
{
    /** Tables of these modules stay. */
    public const KEPT_PREFIXES = ['premium_', 'custom_'];

    /**
     * Whether a legacy table (its original name) is removed here. The obsolete tables are not: they have their own
     * step, which drops them children first.
     *
     * @param  array<string, true>  $moduleTables  what the modules that stay create (LegacyModuleTables)
     */
    public static function removable(string $table, array $moduleTables = []): bool
    {
        if (isset($moduleTables[$table])) {
            return false;
        }
        foreach (self::KEPT_PREFIXES as $prefix) {
            if (str_starts_with(strtolower($table), $prefix)) {
                return false;
            }
        }

        return ! in_array($table, MigrationVerifier::OBSOLETE_TABLES, true);
    }

    /**
     * @param  list<string>  $tables  namespaced names
     * @return list<string> the tables dropped (an absent one counts as gone)
     */
    public function dropTables(PDO $pdo, array $tables): array
    {
        foreach ($tables as $table) {
            if (preg_match('/^legacy_[A-Za-z0-9_$]+$/D', $table) !== 1) {
                throw new RuntimeException("Refusing to drop '{$table}': it is not a namespaced legacy table.");
            }
        }
        if ($tables === []) {
            return [];
        }
        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $previous = $sqlite ? (string) $pdo->query('PRAGMA foreign_keys')->fetchColumn() : (string) $pdo->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn();
        $set = static fn (string $value): string => $sqlite ? "PRAGMA foreign_keys = {$value}" : "SET FOREIGN_KEY_CHECKS = {$value}";
        $pdo->exec($set('0'));
        try {
            foreach ($tables as $table) {
                $pdo->exec('DROP TABLE IF EXISTS `'.$table.'`');
            }
        } finally {
            $pdo->exec($set($previous === '0' ? '0' : '1'));
        }

        return $tables;
    }
}
