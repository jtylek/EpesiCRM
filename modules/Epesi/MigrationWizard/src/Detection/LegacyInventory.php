<?php

namespace Epesi\Modules\MigrationWizard\Detection;

use App\Services\LegacyImport\LegacyStockColumns;
use FilesystemIterator;
use PDO;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Builds the coverage inventory ModuleCoverage::report() consumes from a legacy
 * database and installation directory. Read-only: only SELECTs run, nothing is
 * executed from the legacy tree and links are never followed.
 *
 * The version comes from the database (`variables.version`, a PHP-serialized
 * string that is parsed, never unserialized) and, when it survived, from
 * include/version.php (parsed, never included). Both sources must exist and agree:
 * when they differ, or either is missing, the version is 'unknown' so no
 * approved schema can match it (a half-overwritten tree is not an approved release).
 */
final class LegacyInventory
{
    /** Key for loose files directly under data/ (config, markers, ...). */
    public const LOOSE_DATA_FILES = 'data/*';

    /** Files counted per scan; past it the inventory is marked incomplete (the real file decisions never rely on it). */
    public const MAX_FILES = 1000000;

    public function __construct(private readonly SchemaFingerprint $fingerprint = new SchemaFingerprint) {}

    /**
     * @param  array<string, string>  $renameMap  original => current name, once the wizard has namespaced
     *                                            the tables: only those tables are the legacy ones, and they
     *                                            are reported under their original names so the schema
     *                                            fingerprint and the coverage rules still apply
     * @return array<string, mixed>
     */
    public function build(PDO $pdo, string $legacyRoot, int $maxFiles = self::MAX_FILES, array $renameMap = []): array
    {
        $current = static fn (string $name): string => $renameMap === [] ? $name : (string) ($renameMap[$name] ?? $name);
        $columns = [];
        foreach ($pdo->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION')->fetchAll() as $row) {
            $columns[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
        }
        $statistics = [];
        foreach ($pdo->query('SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\' ORDER BY TABLE_NAME')->fetchAll() as $stat) {
            $statistics[$stat['TABLE_NAME']] = [
                'estimated_rows' => (int) $stat['TABLE_ROWS'],
                'estimated_bytes' => (int) $stat['DATA_LENGTH'] + (int) $stat['INDEX_LENGTH'],
                'engine' => $stat['ENGINE'],
            ];
        }
        $columns = self::remap($columns, $renameMap);
        $statistics = self::remap($statistics, $renameMap);
        $modules = isset($columns['modules']) ? $pdo->query('SELECT name, version FROM `'.str_replace('`', '', $current('modules')).'` ORDER BY name')->fetchAll() : [];
        $databaseVersion = null;
        if (isset($columns['variables'])) {
            $statement = $pdo->prepare('SELECT value FROM `'.str_replace('`', '', $current('variables')).'` WHERE name = ?');
            $statement->execute(['version']);
            $value = $statement->fetchColumn();
            $databaseVersion = is_string($value) ? self::parseSerializedVersion($value) : null;
        }
        [$files, $incomplete, $links] = $this->scanDataAreas($legacyRoot.'/data', $maxFiles);

        return $this->fromMetadata($columns, $statistics, $modules, $databaseVersion, self::readFileVersion($legacyRoot), $files, $this->recordFields($pdo, $columns, $current))
            + ['file_scan_incomplete' => $incomplete, 'skipped_links' => $links];
    }

    /**
     * For each recordset whose extra columns become custom fields: the column types of `<tab>_data_1`, the
     * rows of `<tab>_field` (the declared fields) and of `<tab>_callback`. SELECT only. A missing table
     * leaves that part empty; a failure never fails the assessment (the planner then types from the columns).
     *
     * @param  array<string, list<string>>  $columns  table (original names) => columns
     * @param  callable(string): string  $current  original => current table name
     * @return array<string, array{column_types: array<string, array{type_name: string, type: string}>, fields: list<array<string, mixed>>, callbacks: array<string, list<string>>}>
     */
    private function recordFields(PDO $pdo, array $columns, callable $current): array
    {
        $quote = static fn (string $name): string => '`'.str_replace('`', '', $name).'`';
        $result = [];
        foreach (LegacyStockColumns::RECORDSET_TABLES as $table => $tab) {
            if (! isset($columns[$table])) {
                continue;
            }
            $info = ['column_types' => [], 'fields' => [], 'callbacks' => []];
            try {
                $statement = $pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
                $statement->execute([$current($table)]);
                foreach ($statement->fetchAll() as $row) {
                    $info['column_types'][(string) $row['COLUMN_NAME']] = ['type_name' => (string) $row['DATA_TYPE'], 'type' => (string) $row['COLUMN_TYPE']];
                }
                if (isset($columns[$tab.'_field'])) {
                    $info['fields'] = array_map(static fn (array $row): array => array_change_key_case($row, CASE_LOWER), $pdo->query(
                        'SELECT field, caption, type, param, visible, required, filter, export, active, position, help FROM '.$quote($current($tab.'_field')).' ORDER BY position',
                    )->fetchAll());
                }
                if (isset($columns[$tab.'_callback'])) {
                    foreach ($pdo->query('SELECT field, callback FROM '.$quote($current($tab.'_callback')))->fetchAll() as $row) {
                        $info['callbacks'][(string) $row['field']][] = (string) $row['callback'];
                    }
                }
            } catch (Throwable) {
                // Typed from the columns alone.
            }
            $result[$tab] = $info;
        }

        return $result;
    }

    /**
     * Keep only the tables the rename map names (by their current name) and report
     * them under their original names. An empty map changes nothing.
     *
     * @template T
     *
     * @param  array<string, T>  $byCurrentName
     * @param  array<string, string>  $renameMap  original => current
     * @return array<string, T>
     */
    public static function remap(array $byCurrentName, array $renameMap): array
    {
        if ($renameMap === []) {
            return $byCurrentName;
        }
        $original = array_flip(array_map('strval', $renameMap));
        $result = [];
        foreach ($byCurrentName as $name => $value) {
            if (isset($original[(string) $name])) {
                $result[(string) $original[(string) $name]] = $value;
            }
        }

        return $result;
    }

    /**
     * Pure assembly, separated so it can be tested without a database.
     *
     * @param  array<string, list<string>>  $columns  table => columns
     * @param  array<string, array<string, mixed>>  $statistics  table => estimated_rows/estimated_bytes/engine
     * @param  list<array<string, mixed>>  $modules  rows of the legacy `modules` table
     * @param  array<string, array{count: int, bytes: int}>  $files
     * @return array<string, mixed>
     */
    public function fromMetadata(array $columns, array $statistics, array $modules, ?string $databaseVersion, ?string $fileVersion, array $files, array $recordFields = []): array
    {
        $tables = [];
        foreach ($columns as $name => $list) {
            $tables[(string) $name] = ['columns' => array_values($list)] + [
                'estimated_rows' => (int) ($statistics[$name]['estimated_rows'] ?? 0),
                'estimated_bytes' => (int) ($statistics[$name]['estimated_bytes'] ?? 0),
                'engine' => $statistics[$name]['engine'] ?? null,
            ];
        }
        ksort($tables, SORT_STRING);
        $mismatch = $databaseVersion !== null && $fileVersion !== null && $databaseVersion !== $fileVersion;
        $version = $databaseVersion !== null && $fileVersion !== null && ! $mismatch ? $databaseVersion : null;

        return [
            'version' => $version ?? 'unknown',
            'version_sources' => ['database' => $databaseVersion, 'file' => $fileVersion],
            'version_mismatch' => $mismatch,
            'fingerprint' => $this->fingerprint->hash(array_map(static fn (array $table): array => $table['columns'], $tables)),
            'modules' => array_map(static fn (array $row): array => ['name' => (string) $row['name'], 'version' => $row['version']], $modules),
            'tables' => $tables,
            'files' => $files,
            // Only for the recordsets whose extra columns become custom fields (LegacyStockColumns::RECORDSET_TABLES).
            'record_fields' => $recordFields,
        ];
    }

    public static function parseSerializedVersion(string $value): ?string
    {
        if (preg_match('/^s:\d+:"([0-9A-Za-z.\-_]+)";$/D', $value, $match) === 1) {
            return $match[1];
        }

        return preg_match('/^[0-9]+(\.[0-9]+)*$/D', $value) === 1 ? $value : null;
    }

    public static function readFileVersion(string $legacyRoot): ?string
    {
        $path = $legacyRoot.'/include/version.php';
        if (! is_file($path) || is_link($path) || ! is_readable($path)) {
            return null;
        }
        $source = (string) file_get_contents($path, false, null, 0, 65536);

        return preg_match('/define\(\s*[\'"]EPESI_VERSION[\'"]\s*,\s*[\'"]([0-9A-Za-z.\-_]+)[\'"]\s*\)/', $source, $match) === 1 ? $match[1] : null;
    }

    /**
     * One area per top-level directory of data/, plus data/* for loose files.
     *
     * @return array{0: array<string, array{count: int, bytes: int}>, 1: bool, 2: int}
     */
    public function scanDataAreas(string $dataDir, int $maxFiles): array
    {
        $files = [];
        $incomplete = false;
        $links = 0;
        $seen = 0;
        if (! is_dir($dataDir) || is_link($dataDir)) {
            return [$files, true, 0];
        }
        $entries = @scandir($dataDir);
        if ($entries === false) {
            return [$files, true, 0];
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dataDir.'/'.$entry;
            if (is_link($path)) {
                $links++;

                continue;
            }
            if (is_file($path)) {
                $area = self::LOOSE_DATA_FILES;
                $files[$area] ??= ['count' => 0, 'bytes' => 0];
                $files[$area]['count']++;
                $files[$area]['bytes'] += (int) @filesize($path);
                $seen++;

                continue;
            }
            if (! is_dir($path)) {
                continue;
            }
            $area = 'data/'.$entry;
            $files[$area] = ['count' => 0, 'bytes' => 0];
            if ($seen >= $maxFiles) {
                // Past the cap nothing is counted, but an area is never left out of the inventory:
                // one that holds anything is listed as non-empty (count 1, size unknown).
                $incomplete = true;
                $files[$area]['count'] = (@scandir($path) ?: []) === ['.', '..'] ? 0 : 1;

                continue;
            }
            try {
                // Scratch folders (caches, logs, Roundcube's temporary files) are neither walked nor counted.
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveCallbackFilterIterator(
                        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                        static fn ($current): bool => ! ($current->isDir() && ScratchAreas::skips($dataDir, $current->getPathname())),
                    ),
                    RecursiveIteratorIterator::LEAVES_ONLY,
                    RecursiveIteratorIterator::CATCH_GET_CHILD,
                );
                foreach ($iterator as $file) {
                    if ($file->isLink()) {
                        $links++;

                        continue;
                    }
                    if (! $file->isFile()) {
                        continue;
                    }
                    $files[$area]['count']++;
                    $files[$area]['bytes'] += (int) $file->getSize();
                    if (++$seen >= $maxFiles) {
                        $incomplete = true;
                        break;
                    }
                }
            } catch (Throwable) {
                $incomplete = true;
            }
        }
        ksort($files, SORT_STRING);

        return [$files, $incomplete, $links];
    }
}
