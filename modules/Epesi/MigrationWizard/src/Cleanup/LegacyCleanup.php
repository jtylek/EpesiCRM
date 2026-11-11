<?php

namespace Epesi\Modules\MigrationWizard\Cleanup;

use Epesi\Modules\MigrationWizard\Import\ImportProgress;
use PDO;
use PharData;
use RuntimeException;

/**
 * Final cleanup: removes what is not Epesi 3, not hosting-owned and not preserved. Destructive, therefore:
 *
 *  - off unless the caller passes `enabled` (the feature flag);
 *  - refuses unless the operator confirmed the pre-upgrade backup (never
 *    assumed), and MigrationVerifier says cleanup_allowed;
 *  - needs the list of files the release ships (CleanupManifests); without it nothing is classified, so
 *    nothing is ever "everything not listed";
 *  - KEEPS: the release's files, Epesi 3's runtime, installed Epesi 3 modules, hosting-owned paths
 *    (.well-known/, cgi-bin/, .user.ini, php.ini, ...; reported), every data/ area that was not migrated
 *    (data/config.php included), every file still in CRM_Mail / Utils_FileStorage, and every legacy
 *    modules/ directory whose module no completed importer migrated (Premium and Custom among them);
 *  - REMOVES: legacy modules/ directories of migrated modules, the legacy root files and folders, and
 *    anything in vendor/ the release does not list. A path that is none of these (user-added) is archived
 *    first, in a hashed archive verified before the first deletion, and a size cap refuses an archive
 *    that is too large rather than copying a whole site;
 *  - drops only legacy tables the verifier released by count and that appear in the exact rename map; a
 *    table another table still references by foreign key stays, with its reason, and so does everything
 *    else; one that stays never stops the rest;
 *  - writes a log of every removed path before the first deletion, and removes files individually by
 *    classified relative path, unlinking links instead of following them;
 *  - rescans and fails when executable code that should have gone remains.
 *
 * It does not write the installed marker, change .env or lift maintenance mode; those are the
 * wizard's final transition after this returns.
 */
final class LegacyCleanup
{
    /** Archive nothing bigger than this: a user-added path of that size is somebody's site or backup, not a stray file. */
    public const ARCHIVE_MAX_FILES = 20000;

    public const ARCHIVE_MAX_BYTES = 209715200;

    public function __construct(
        private readonly LegacyFileClassifier $classifier,
        private readonly bool $enabled = false,
    ) {}

    /**
     * The data/ areas whose empty directories cleanup removes.
     *
     * @return list<string>
     */
    public static function emptiedAreas(): array
    {
        return [...LegacyFileClassifier::PROTECTED_AREAS, ...array_map(static fn (string $folder): string => 'data/'.$folder, LegacyJunk::PRUNE_ONLY)];
    }

    /**
     * Preview of what execute() would do; never changes anything. Given the database it also says which
     * tables a foreign key keeps.
     *
     * @param  array{release: list<string>, options?: array<string, mixed>}  $manifests
     * @param  array<string, mixed>  $verification  MigrationVerifier::verify() output
     * @param  array<string, string>  $renameMap  old => new
     * @return array<string, mixed>
     */
    public function plan(string $root, array $manifests, array $verification, array $renameMap, ?PDO $pdo = null): array
    {
        $classification = $this->classify($root, $manifests, $verification);
        [$archive, $remove] = $this->split($classification['entries'], $verification);
        $tables = $pdo === null
            ? ['drop' => array_values($this->tablesToDrop($verification, $renameMap)), 'kept' => []]
            : $this->tableDecision($pdo, $verification, $renameMap);

        return [
            'cleanup_allowed' => (bool) ($verification['cleanup_allowed'] ?? false),
            'enabled' => $this->enabled,
            'drop_tables' => $tables['drop'],
            'kept_tables' => $tables['kept'],
            'archive_paths' => $archive,
            'remove_paths' => $remove,
            'counts' => $classification['counts'],
            'hosting_kept' => $classification['hosting_kept'],
            'backups_left_in_place' => $classification['backups_left_in_place'],
            'modules_kept' => $this->modulesKept($classification['entries']),
            'executable_outside_manifest' => $classification['executable_outside_manifest'],
            'preserved_executable' => $classification['preserved_executable'],
            'preserved_tables' => $verification['preserved_tables'] ?? [],
        ];
    }

    /**
     * @param  array{release: list<string>, options?: array<string, mixed>}  $manifests
     * @param  array<string, mixed>  $verification
     * @param  array<string, string>  $renameMap  old => new, the exact map LegacyTableNamespace applied
     * @return array<string, mixed>
     */
    public function execute(
        string $root,
        string $archiveDirectory,
        array $manifests,
        array $verification,
        array $renameMap,
        PDO $pdo,
        bool $backupVerified,
    ): array {
        if (! $this->enabled) {
            throw new CleanupRefused('Legacy cleanup is disabled.');
        }
        if (! $backupVerified) {
            throw new CleanupRefused('Legacy cleanup requires the confirmed pre-upgrade backup.');
        }
        if (($verification['cleanup_allowed'] ?? false) !== true || ($verification['status'] ?? null) === 'block') {
            throw new CleanupRefused('Verification does not allow cleanup.');
        }
        $realRoot = realpath($root);
        $realArchive = realpath($archiveDirectory);
        // Outside the installation, or in its private storage/app/ (never served, and excluded from the cleanup itself).
        $inStorage = $realRoot !== false && $realArchive !== false && str_starts_with(str_replace('\\', '/', $realArchive).'/', str_replace('\\', '/', $realRoot).'/storage/app/');
        if ($realRoot === false || $realArchive === false || ! is_dir($realArchive) || ! is_writable($realArchive)
            || $realArchive === $realRoot || (str_starts_with(str_replace('\\', '/', $realArchive).'/', str_replace('\\', '/', $realRoot).'/') && ! $inStorage)) {
            throw new CleanupRefused('The leftovers archive must be an existing writable directory outside the installation or in its storage/app/.');
        }

        // Until the first deletion every failure is a refusal: nothing was changed, so cleanup can simply be run again.
        try {
            $classification = $this->classify($realRoot, $manifests, $verification, $realArchive);
            [$archivePaths, $removePaths] = $this->split($classification['entries'], $verification);
            $this->assertSafeToRemove($removePaths);
            $this->assertArchivable($realRoot, $archivePaths);
            $tables = $this->tableDecision($pdo, $verification, $renameMap);

            // 0. Say what will go, before anything does.
            $id = gmdate('YmdHis').'-'.bin2hex(random_bytes(4));
            $log = $this->writeLog($realArchive, $id, $removePaths, $archivePaths, $tables, $classification);

            // 1. Preserve what will not exist anywhere else, and verify it before the first deletion.
            $archive = $archivePaths === [] ? null : $this->archive($realRoot, $realArchive, $archivePaths, $id);
        } catch (CleanupRefused $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new CleanupRefused($e->getMessage(), 0, $e);
        }

        // 2. Drop only the released tables, named exactly by the rename map, children first.
        ImportProgress::stage('Data cleanup: removing the migrated old tables');
        $dropped = $this->dropTables($pdo, $tables['drop']);

        // 3. Remove classified files, links unlinked and never followed.
        $removed = $this->remove($realRoot, $removePaths);
        ImportProgress::stage('Data cleanup: checking that nothing is left behind');
        // The importers moved the files out of these areas and legacy left Utils_Attachment's folders behind: only the
        // directories that are now empty go. A file still in them is never touched here.
        $this->pruneEmptyDirectories($realRoot, self::emptiedAreas());

        // 4. Rescan: nothing that should have gone may remain.
        $after = $this->classify($realRoot, $manifests, $verification, $realArchive);
        if ($after['executable_outside_manifest'] !== []) {
            throw new RuntimeException('Executable files that should have been removed remain: '.implode(', ', array_slice($after['executable_outside_manifest'], 0, 10)));
        }

        return [
            'archive' => $archive,
            'log' => $log,
            'dropped_tables' => $dropped,
            'kept_tables' => $tables['kept'],
            'removed_files' => $removed,
            'hosting_kept' => $after['hosting_kept'],
            'backups_left_in_place' => $after['backups_left_in_place'],
            'modules_kept' => $this->modulesKept($after['entries']),
            'preserved_tables' => $verification['preserved_tables'] ?? [],
            'remaining_non_manifest_files' => count($after['entries']) - $after['counts'][LegacyFileClassifier::KEEP],
            'preserved_files' => $after['counts'][LegacyFileClassifier::PRESERVED],
            'preserved_executable' => $after['preserved_executable'],
        ];
    }

    /**
     * Verifier-released tables mapped to their namespaced name. A released table
     * missing from the exact map is an error: it is never guessed from a prefix.
     *
     * @return array<string, string> original name => namespaced name
     */
    private function tablesToDrop(array $verification, array $renameMap): array
    {
        $drops = [];
        foreach ($verification['cleanup_eligible_tables'] ?? [] as $table) {
            $table = (string) $table;
            if (! isset($renameMap[$table])) {
                throw new RuntimeException("Table '{$table}' is released for cleanup but is not in the rename map.");
            }
            $drops[$table] = (string) $renameMap[$table];
        }

        return $drops;
    }

    /**
     * Which of the released tables are dropped, in which order, and which stay and why. A table that
     * another table (one that stays) references by foreign key cannot go; a metadata table goes only with
     * its certified table. What stays is reported; nothing that stays stops the rest.
     *
     * @return array{drop: list<string>, kept: array<string, string>} namespaced names in drop order; original name => reason
     */
    private function tableDecision(PDO $pdo, array $verification, array $renameMap): array
    {
        $candidates = $this->tablesToDrop($verification, $renameMap);
        $existing = $this->tables($pdo);
        $keep = [];
        $drop = [];
        foreach ($candidates as $original => $legacy) {
            if (isset($existing[$legacy])) {
                $drop[$original] = $legacy;
            }
        }
        $children = $this->referencedBy($pdo, $existing);
        $byLegacy = array_flip($renameMap);
        $groups = (array) ($verification['table_groups'] ?? []);
        do {
            $changed = false;
            foreach ($drop as $original => $legacy) {
                $blockers = array_values(array_filter($children[$legacy] ?? [], static fn (string $child): bool => ! in_array($child, $drop, true)));
                if ($blockers !== []) {
                    $keep[$original] = 'still referenced by '.implode(', ', array_map(static fn (string $child): string => $byLegacy[$child] ?? $child, array_slice($blockers, 0, 5)));
                    unset($drop[$original]);
                    $changed = true;
                }
            }
            foreach ($groups as $parent => $sides) {
                if (isset($keep[$parent]) || (isset($candidates[$parent]) && ! isset($drop[$parent]) && isset($existing[$candidates[$parent]]))) {
                    foreach ((array) $sides as $side) {
                        if (isset($drop[$side])) {
                            $keep[$side] = "its table {$parent} is kept";
                            unset($drop[$side]);
                            $changed = true;
                        }
                    }
                }
            }
        } while ($changed);

        // Children before parents, so foreign keys never stop a drop.
        $ordered = [];
        $left = array_values($drop);
        while ($left !== []) {
            $progress = false;
            foreach ($left as $i => $table) {
                if (array_intersect($children[$table] ?? [], $left) === []) {
                    $ordered[] = $table;
                    unset($left[$i]);
                    $progress = true;
                }
            }
            if (! $progress) {
                foreach ($left as $table) {
                    $keep[$byLegacy[$table] ?? $table] = 'its foreign keys form a cycle';
                }
                break;
            }
            $left = array_values($left);
        }

        return ['drop' => $ordered, 'kept' => $keep];
    }

    /**
     * Tables that reference each table by foreign key (a table's own self-reference is not a blocker).
     *
     * @param  array<string, true>  $existing
     * @return array<string, list<string>> referenced table => referencing tables
     */
    private function referencedBy(PDO $pdo, array $existing): array
    {
        $children = [];
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach (array_keys($existing) as $table) {
                foreach ($pdo->query('PRAGMA foreign_key_list("'.str_replace('"', '', $table).'")')->fetchAll(PDO::FETCH_ASSOC) as $key) {
                    if ($key['table'] !== $table) {
                        $children[$key['table']][] = $table;
                    }
                }
            }
        } else {
            $rows = $pdo->query('SELECT DISTINCT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')->fetchAll(PDO::FETCH_NUM);
            foreach ($rows as [$table, $parent]) {
                if ($table !== $parent) {
                    $children[(string) $parent][] = (string) $table;
                }
            }
        }

        return array_map(static fn (array $list): array => array_values(array_unique($list)), $children);
    }

    /**
     * The files to archive and to remove, from the classification. What stays is not in either list.
     *
     * @param  array<string, array<string, mixed>>  $entries
     * @param  array<string, mixed>  $verification
     * @return array{0: list<string>, 1: list<string>} paths to archive, paths to remove
     */
    private function split(array $entries, array $verification): array
    {
        $discarded = array_map('strval', $verification['discarded_file_areas'] ?? []);
        $archive = [];
        $remove = [];
        foreach ($entries as $path => $entry) {
            $path = (string) $path;
            switch ($entry['class']) {
                case LegacyFileClassifier::LEGACY_CODE:
                    $remove[] = $path; // legacy code is in the pre-upgrade backup
                    break;
                case LegacyFileClassifier::UNKNOWN:
                    $remove[] = $path;
                    if (! $entry['link']) {
                        $archive[] = $path; // nobody listed it, so it is kept in the hashed archive
                    }
                    break;
                case LegacyFileClassifier::LEGACY_DATA:
                    $remove[] = $path; // released by the verifier: migrated and ledger-verified, or discarded by name
                    if (! $entry['link'] && in_array(LegacyFileClassifier::dataArea($path), $discarded, true) && ! in_array(LegacyFileClassifier::dataArea($path), LegacyFileClassifier::UNARCHIVED_AREAS, true)) {
                        $archive[] = $path;
                    }
                    break;
                default:
                    // keep, hosting, preserved: stay exactly where they are.
            }
        }

        return [$archive, $remove];
    }

    /** @param  list<string>  $removePaths */
    private function assertSafeToRemove(array $removePaths): void
    {
        $never = [...CleanupManifests::MUST_SHIP, '.env', '.htaccess', 'index.php', 'cron.php'];
        foreach ($removePaths as $path) {
            if (in_array($path, $never, true) || str_starts_with($path, 'app/') || str_starts_with($path, 'storage/') || str_starts_with($path, 'public/') || str_starts_with($path, 'bootstrap/')) {
                throw new RuntimeException("Cleanup refused: it would remove {$path}, which Epesi 3 itself needs.");
            }
        }
    }

    /**
     * @param  list<string>  $archivePaths
     */
    private function assertArchivable(string $root, array $archivePaths): void
    {
        $bytes = 0;
        foreach ($archivePaths as $path) {
            $bytes += (int) @filesize($root.'/'.$path);
        }
        if (count($archivePaths) > self::ARCHIVE_MAX_FILES || $bytes > self::ARCHIVE_MAX_BYTES) {
            $tops = [];
            foreach ($archivePaths as $path) {
                $top = explode('/', $path)[0];
                $tops[$top] = ($tops[$top] ?? 0) + 1;
            }
            arsort($tops);
            $names = implode(', ', array_map(static fn (string $top, int $n): string => "{$top} ({$n} files)", array_keys(array_slice($tops, 0, 10, true)), array_slice($tops, 0, 10, true)));
            throw new RuntimeException('Cleanup refused: '.count($archivePaths).' files ('.round($bytes / 1048576).' MB) that nothing lists would have to be archived before removal (limit '.self::ARCHIVE_MAX_FILES.' files, '.round(self::ARCHIVE_MAX_BYTES / 1048576).' MB). That is more than stray files, probably a copy or a backup of the site. Move these paths outside the Epesi directory and run it again: '.$names.'.');
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     * @return int the number of legacy module directories (modules/<Vendor>/<Name>) that stay
     */
    private function modulesKept(array $entries): int
    {
        $modules = [];
        foreach ($entries as $path => $entry) {
            if (($entry['action'] ?? null) === 'module_not_migrated' && ! str_starts_with((string) $path, 'modules/Epesi/')) {
                $segments = explode('/', (string) $path);
                $modules[implode('/', array_slice($segments, 0, min(3, count($segments) - 1)))] = true;
            }
        }

        return count($modules);
    }

    /**
     * @param  array{release: list<string>, options?: array<string, mixed>}  $manifests
     * @param  array<string, mixed>  $verification
     * @return array<string, mixed>
     */
    private function classify(string $root, array $manifests, array $verification, ?string $archiveDirectory = null): array
    {
        $options = $manifests['options'] ?? [];
        if ($archiveDirectory !== null) {
            $options['exclude'] = [...($options['exclude'] ?? []), $archiveDirectory];
        }
        $releasable = array_values(array_unique([
            ...array_map('strval', $verification['cleanup_eligible_file_areas'] ?? []),
            ...array_map('strval', $verification['discarded_file_areas'] ?? []),
        ]));
        $options['releasable_areas'] = $releasable;

        return $this->classifier->classify($root, $manifests['release'], $options);
    }

    /**
     * @param  list<string>  $remove
     * @param  list<string>  $archive
     * @param  array{drop: list<string>, kept: array<string, string>}  $tables
     * @param  array<string, mixed>  $classification
     */
    private function writeLog(string $directory, string $id, array $remove, array $archive, array $tables, array $classification): string
    {
        $path = $directory.'/legacy-cleanup-'.$id.'.log';
        $lines = ['# Legacy cleanup '.$id, '# Written before the first deletion. Every path below is removed.'];
        foreach ($remove as $removed) {
            $lines[] = 'remove '.$removed;
        }
        foreach ($archive as $archived) {
            $lines[] = 'archived '.$archived;
        }
        foreach ($tables['drop'] as $table) {
            $lines[] = 'drop-table '.$table;
        }
        foreach ($tables['kept'] as $table => $reason) {
            $lines[] = 'keep-table '.$table.' ('.$reason.')';
        }
        foreach ($classification['hosting_kept'] as $kept) {
            $lines[] = 'keep-hosting '.$kept;
        }
        if (file_put_contents($path, implode("\n", $lines)."\n") === false) {
            throw new RuntimeException('Cleanup refused: its log could not be written.');
        }
        chmod($path, 0600);

        return $path;
    }

    /**
     * Remove the empty directories below the given data areas (and the area directory itself when nothing is left
     * in it), bottom-up, never following a link and never removing a directory that holds anything.
     *
     * @param  list<string>  $areas  data/<dir>
     */
    private function pruneEmptyDirectories(string $root, array $areas): void
    {
        foreach ($areas as $area) {
            $base = $root.'/'.$area;
            if (is_link($base) || ! is_dir($base)) {
                continue;
            }
            $this->pruneBelow($base);
            if ((@scandir($base) ?: []) === ['.', '..']) {
                @rmdir($base);
            }
        }
    }

    private function pruneBelow(string $directory): void
    {
        foreach (@scandir($directory) ?: [] as $name) {
            $path = $directory.'/'.$name;
            if ($name === '.' || $name === '..' || is_link($path) || ! is_dir($path)) {
                continue;
            }
            $this->pruneBelow($path);
            if ((@scandir($path) ?: []) === ['.', '..']) {
                @rmdir($path);
            }
        }
    }

    /** @param  list<string>  $paths */
    private function archive(string $root, string $directory, array $paths, string $id): array
    {
        $tar = $directory.'/legacy-leftovers-'.$id.'.tar';
        $manifestPath = $directory.'/legacy-leftovers-'.$id.'.json';
        $hashes = [];
        $archive = new PharData($tar);
        try {
            foreach ($paths as $path) {
                $full = $root.'/'.$path;
                if (is_link($full) || ! is_file($full) || ! is_readable($full)) {
                    throw new RuntimeException("Cannot archive '{$path}' before removal.");
                }
                $archive->addFile($full, $path);
                $hashes[$path] = hash_file('sha256', $full);
            }
            unset($archive);
            chmod($tar, 0600);
            $verify = new PharData($tar);
            $count = 0;
            foreach (new \RecursiveIteratorIterator($verify) as $unused) {
                $count++;
            }
            if ($count !== count($paths)) {
                throw new RuntimeException('The leftovers archive does not contain every file.');
            }
        } catch (\Throwable $e) {
            unset($archive, $verify);
            @unlink($tar);
            throw $e;
        }
        $sha = hash_file('sha256', $tar);
        file_put_contents($manifestPath, json_encode(['archive' => basename($tar), 'sha256' => $sha, 'files' => $hashes], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        chmod($manifestPath, 0600);

        return ['path' => $tar, 'manifest' => $manifestPath, 'sha256' => $sha, 'files' => count($paths)];
    }

    /** @param  list<string>  $paths */
    private function remove(string $root, array $paths): int
    {
        $removed = 0;
        $directories = [];
        $total = count($paths);
        foreach (array_values($paths) as $i => $path) {
            ImportProgress::stage('Data cleanup: removing old files', $i + 1, $total);
            $full = $root.'/'.$path;
            $parent = realpath(dirname($full));
            // The parent must still be inside the root; the entry itself may be a link, which is unlinked, not followed.
            // Compared with forward slashes: realpath() gives backslashes on Windows.
            $parentPath = $parent === false ? null : str_replace('\\', '/', $parent);
            $rootPath = str_replace('\\', '/', $root);
            if ($parentPath === null || ($parentPath !== $rootPath && ! str_starts_with($parentPath.'/', $rootPath.'/'))) {
                throw new RuntimeException("Refusing to remove '{$path}': it resolves outside the installation.");
            }
            if (is_link($full) || is_file($full)) {
                if (! @unlink($full)) {
                    throw new RuntimeException("Could not remove '{$path}'.");
                }
                $removed++;
            }
            for ($dir = dirname($path); $dir !== '.' && $dir !== ''; $dir = dirname($dir)) {
                $directories[$dir] = substr_count($dir, '/');
            }
        }
        arsort($directories);
        foreach (array_keys($directories) as $dir) {
            $full = $root.'/'.$dir;
            if (is_dir($full) && ! is_link($full) && (@scandir($full) ?: []) === ['.', '..']) {
                @rmdir($full);
            }
        }

        return $removed;
    }

    /**
     * Drop exactly these tables with foreign key checks off on THIS connection only (a recordset's side tables
     * reference each other and their parent). The previous setting is restored in a finally block, so it is never
     * left off after a failure; the setting belongs to the session, so a dead process cannot leave it off either.
     *
     * @param  list<string>  $tables  namespaced names, children first
     * @return list<string>
     */
    private function dropTables(PDO $pdo, array $tables): array
    {
        if ($tables === []) {
            return [];
        }
        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $previous = $sqlite
            ? (string) $pdo->query('PRAGMA foreign_keys')->fetchColumn()
            : (string) $pdo->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn();
        $set = static fn (string $value): string => $sqlite ? "PRAGMA foreign_keys = {$value}" : "SET FOREIGN_KEY_CHECKS = {$value}";
        $dropped = [];
        $pdo->exec($set('0'));
        try {
            foreach ($tables as $table) {
                $pdo->exec('DROP TABLE '.$this->quote($table));
                $dropped[] = $table;
            }
        } finally {
            $pdo->exec($set($previous === '0' ? '0' : '1'));
        }

        return $dropped;
    }

    /** @return array<string, true> */
    private function tables(PDO $pdo): array
    {
        $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "SELECT name FROM sqlite_master WHERE type = 'table'"
            : 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\'';

        return array_fill_keys(array_map('strval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    private function quote(string $identifier): string
    {
        if (! preg_match('/^legacy_[A-Za-z0-9_$]+$/D', $identifier)) {
            throw new RuntimeException('Refusing to drop a table outside the legacy namespace.');
        }

        return '`'.$identifier.'`';
    }
}
