<?php

namespace Epesi\Modules\MigrationWizard\Database;

use Epesi\Modules\MigrationWizard\Verification\MigrationVerifier;
use PDO;
use RuntimeException;

/**
 * The mechanics of dropping the built-in obsolete legacy tables (MigrationVerifier::OBSOLETE_TABLES).
 *
 * Only namespaced tables are touched (`legacy_<name>`, the names the exact rename map gave them); a live
 * table of Epesi 3 or of its own Roundcube that happens to share the original name (rc_users, rc_session)
 * is never named here. The pre-upgrade backup holds every one of them.
 */
final class ObsoleteLegacyTables
{
    /**
     * Children before parents, so foreign keys never stop a drop: rc_users is the parent of the cache,
     * contact, dictionary, identity and search tables, and rc_contactgroupmembers points at the contact
     * tables. Every name in MigrationVerifier::OBSOLETE_TABLES is here exactly once.
     */
    public const DROP_ORDER = [
        'rc_contactgroupmembers',
        'rc_contacts',
        'rc_contactgroups',
        'rc_identities',
        'rc_dictionary',
        'rc_searches',
        'rc_cache_messages',
        'rc_cache_thread',
        'rc_cache_index',
        'rc_cache',
        'rc_session',
        'rc_system',
        'rc_cache_shared',
        'recordbrowser_search_index',
        'rc_users',
    ];

    /**
     * The obsolete tables among $names, in drop order, nothing else.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public static function ordered(array $names): array
    {
        return array_values(array_filter(self::DROP_ORDER, static fn (string $table): bool => in_array($table, $names, true) && in_array($table, MigrationVerifier::OBSOLETE_TABLES, true)));
    }

    public function exists(PDO $pdo, string $table): bool
    {
        $this->assertNamespaced($table);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
        } else {
            $statement = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME = ?");
        }
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function drop(PDO $pdo, string $table): void
    {
        $this->assertNamespaced($table);
        $pdo->exec('DROP TABLE `'.$table.'`');
    }

    private function assertNamespaced(string $table): void
    {
        if (preg_match('/^legacy_[A-Za-z0-9_$]+$/D', $table) !== 1) {
            throw new RuntimeException('Refusing to touch a table outside the legacy namespace.');
        }
    }
}
