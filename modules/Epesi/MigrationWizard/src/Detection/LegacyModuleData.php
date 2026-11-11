<?php

namespace Epesi\Modules\MigrationWizard\Detection;

use PDO;
use Throwable;

/**
 * Which installed legacy modules own data in this installation, and how much.
 *
 * Legacy Epesi keeps its recordsets (Utils_RecordBrowser) in a registry, the table
 * `recordbrowser_table_properties` (`tab`), plus `recordbrowser_custom_recordsets` for
 * the ones users created. One recordset `<tab>` owns up to ten tables, `<tab>_data_1`
 * (the records) and the companions `_field`, `_access`, `_access_clearance`,
 * `_access_fields`, `_callback`, `_edit_history`, `_edit_history_data`, `_favorite`
 * and `_recent`; the page collapses them into the one recordset. All other data lives
 * in plain tables a module creates with `DB::CreateTable` (user_login, base_login_audit).
 *
 * Ownership comes from the installation's own legacy source, read as text and never
 * executed: a module's `<Name>Install.php` and its `patches/*.php` call
 * `install_new_recordset('<tab>', ...)` and `DB::CreateTable('<table>', ...)` (comments
 * are removed with the PHP tokenizer first). Only the modules in the installation's
 * `modules` table are considered. When a recordset has no declaration in that source
 * (the source tree is missing, or the tab is built at run time) the fallbacks are, in
 * this order: the custom-recordsets registry (owner Utils_RecordBrowser_CustomRecordsets),
 * then the longest installed module name that prefixes the tab (`via` = name, the least
 * certain). Plain tables are never guessed from their names.
 *
 * Counts: active records of a recordset are `SELECT COUNT(*) ... WHERE active=1` and the
 * rows of a plain table `SELECT COUNT(*)`, both exact while the table's estimated size is
 * at most EXACT_COUNT_LIMIT rows; above that the estimate is used and flagged. Read-only.
 */
final class LegacyModuleData
{
    public const RECORDSET_SUFFIXES = ['data_1', 'field', 'access', 'access_clearance', 'access_fields', 'callback', 'edit_history', 'edit_history_data', 'favorite', 'recent'];

    public const EXACT_COUNT_LIMIT = 200000;

    private const MAX_SOURCE_BYTES = 1048576;

    private const CORE_PREFIXES = ['base_', 'crm_', 'utils_', 'libs_', 'data_', 'apps_', 'applets_', 'tools_', 'tests_', 'epesi_'];

    /**
     * @param  array<string, mixed>  $inventory  LegacyInventory::build() output
     * @return array<string, mixed>
     */
    public function build(PDO $pdo, string $legacyRoot, array $inventory): array
    {
        $tables = [];
        foreach ($inventory['tables'] as $name => $table) {
            $tables[(string) $name] = (int) $table['estimated_rows'];
        }
        $names = array_map(static fn (array $module): string => (string) $module['name'], $inventory['modules']);
        $tabs = isset($tables['recordbrowser_table_properties']) ? $this->column($pdo, 'recordbrowser_table_properties', 'tab') : [];
        $custom = isset($tables['recordbrowser_custom_recordsets']) ? $this->column($pdo, 'recordbrowser_custom_recordsets', 'tab') : [];
        $hasActive = static fn (string $table): bool => in_array('active', $inventory['tables'][$table]['columns'] ?? [], true);

        $counter = function (string $kind, string $name) use ($pdo, $tables, $hasActive): array {
            $table = $kind === 'recordset' ? $name.'_data_1' : $name;
            $estimate = $tables[$table] ?? 0;
            if ($estimate <= self::EXACT_COUNT_LIMIT && isset($tables[$table]) && ($kind === 'table' || $hasActive($table))) {
                try {
                    $sql = 'SELECT COUNT(*) FROM `'.str_replace('`', '', $table).'`'.($kind === 'recordset' ? ' WHERE active = 1' : '');

                    return [(int) $pdo->query($sql)->fetchColumn(), true];
                } catch (Throwable) {
                    // fall through to the estimate
                }
            }

            return [$estimate, false];
        };

        return $this->compose($inventory['modules'], $this->sourceOwners($legacyRoot, $names), $tables, $tabs, $custom, $counter);
    }

    /**
     * Pure assembly (no database, no files), separated so it can be tested.
     *
     * @param  list<array{name: string, version: mixed}>  $installed  rows of the legacy `modules` table
     * @param  array<string, array{recordsets: list<string>, tables: list<string>}|null>  $sourceOwners  module => declarations found in its legacy source (null: source not readable)
     * @param  array<string, int>  $tableRows  every table of the database => estimated rows
     * @param  list<string>  $tabs  the recordset registry (recordbrowser_table_properties.tab)
     * @param  list<string>  $customTabs  recordbrowser_custom_recordsets.tab
     * @param  callable(string, string): array{0: int, 1: bool}  $counter  ('recordset'|'table', name) => [count, exact]
     * @return array<string, mixed>
     */
    public function compose(array $installed, array $sourceOwners, array $tableRows, array $tabs, array $customTabs, callable $counter): array
    {
        $tabs = array_values(array_unique(array_map('strval', $tabs)));
        $registered = array_flip($tabs);
        $moduleNames = array_map(static fn (array $module): string => (string) $module['name'], $installed);

        // recordset tab => [module, via]
        $recordsetOwner = [];
        $tableOwner = [];
        // The module's own install code and recordset classes win over another module's patches.
        foreach (['primary_', ''] as $scope) {
            foreach ($moduleNames as $name) {
                foreach ($sourceOwners[$name][$scope.'recordsets'] ?? [] as $tab) {
                    $recordsetOwner[$tab] ??= [$name, 'source'];
                }
                foreach ($sourceOwners[$name][$scope.'tables'] ?? [] as $table) {
                    $tableOwner[$table] ??= $name;
                }
            }
        }
        foreach ($tabs as $tab) {
            if (isset($recordsetOwner[$tab])) {
                continue;
            }
            if (in_array($tab, $customTabs, true) && in_array('Utils_RecordBrowser_CustomRecordsets', $moduleNames, true)) {
                $recordsetOwner[$tab] = ['Utils_RecordBrowser_CustomRecordsets', 'custom'];

                continue;
            }
            $best = null;
            foreach ($moduleNames as $name) {
                $lower = strtolower($name);
                if (($tab === $lower || str_starts_with($tab, $lower.'_')) && ($best === null || strlen($name) > strlen($best))) {
                    $best = $name;
                }
            }
            if ($best !== null) {
                $recordsetOwner[$tab] = [$best, 'name'];
            }
        }

        $perModule = [];
        $unownedRecordsets = 0;
        foreach ($tabs as $tab) {
            if (isset($recordsetOwner[$tab])) {
                $perModule[$recordsetOwner[$tab][0]]['recordsets'][$tab] = $recordsetOwner[$tab][1];
            } else {
                $unownedRecordsets++;
            }
        }
        foreach ($tableOwner as $table => $name) {
            if (isset($tableRows[$table]) && $this->recordsetOf($table, $registered) === $table) {
                $perModule[$name]['tables'][] = $table;
            }
        }

        $versions = array_column($installed, 'version', 'name');
        $modules = [];
        $accounted = [];
        foreach ($perModule as $name => $owned) {
            $recordsets = [];
            $recordsetTables = [];
            foreach ($owned['recordsets'] ?? [] as $tab => $via) {
                [$records, $exact] = $counter('recordset', $tab);
                $recordsets[] = ['tab' => $tab, 'records' => $records, 'exact' => $exact, 'via' => $via];
                foreach (self::RECORDSET_SUFFIXES as $suffix) {
                    if (isset($tableRows[$tab.'_'.$suffix])) {
                        $recordsetTables[] = $tab.'_'.$suffix;
                    }
                }
            }
            $plain = [];
            $plainTables = [];
            foreach ($owned['tables'] ?? [] as $table) {
                $plainTables[] = $table;
                [$rows, $exact] = $counter('table', $table);
                if ($rows > 0) {
                    $plain[] = ['table' => $table, 'rows' => $rows, 'exact' => $exact];
                }
            }
            if ($recordsets === [] && $plain === []) {
                continue;
            }
            usort($recordsets, static fn (array $a, array $b): int => strcmp($a['tab'], $b['tab']));
            usort($plain, static fn (array $a, array $b): int => strcmp($a['table'], $b['table']));
            foreach (array_merge($recordsetTables, $plainTables) as $table) {
                $accounted[$table] = true;
            }
            $modules[] = [
                'name' => (string) $name,
                'version' => (string) ($versions[$name] ?? ''),
                'kind' => $this->kind((string) $name),
                'recordsets' => $recordsets,
                'tables' => $plain,
            ];
        }
        usort($modules, static fn (array $a, array $b): int => strcmp(strtoupper($a['name']), strtoupper($b['name'])));

        return [
            'source_modules_read' => count(array_filter($sourceOwners, static fn ($owner): bool => $owner !== null)),
            'installed_modules' => count($installed),
            'modules' => $modules,
            'table_count' => count($tableRows),
            'accounted_tables' => count($accounted),
            'unaccounted_tables' => count($tableRows) - count($accounted),
            'unaccounted_table_names' => array_values(array_diff(array_keys($tableRows), array_keys($accounted))),
            'unowned_recordsets' => $unownedRecordsets,
        ];
    }

    /**
     * Declarations in each installed module's own legacy source. `primary_*` come from the
     * module's Install file and recordset classes, the others add its patches.
     *
     * @param  list<string>  $moduleNames
     * @return array<string, array{recordsets: list<string>, tables: list<string>, primary_recordsets: list<string>, primary_tables: list<string>}|null>
     */
    public function sourceOwners(string $legacyRoot, array $moduleNames): array
    {
        $owners = [];
        foreach ($moduleNames as $name) {
            $owners[$name] = null;
            if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
                continue;
            }
            $directory = rtrim($legacyRoot, '/\\').'/modules/'.str_replace('_', '/', $name);
            if (! is_dir($directory) || is_link($directory)) {
                continue;
            }
            $segments = explode('_', $name);
            $install = $this->readSource($directory.'/'.end($segments).'Install.php');
            $patches = '';
            if (is_dir($directory.'/patches') && ! is_link($directory.'/patches')) {
                foreach (glob($directory.'/patches/*.php') ?: [] as $file) {
                    $patches .= "\n".($this->readSource($file) ?? '');
                }
            }
            if ($install === null && $patches === '') {
                continue;
            }
            $constants = $this->constants($directory);
            $primary = $this->declarations((string) $install, $constants);
            // RBO recordset classes (extends RBO_Recordset) name their table in table_name().
            foreach (glob($directory.'/*.php') ?: [] as $file) {
                $text = $this->readSource($file);
                if ($text !== null && str_contains($text, 'RBO_Recordset')
                    && preg_match_all('/function\s+table_name\s*\(\s*\)\s*\{\s*return\s*([\'"])([A-Za-z0-9_]+)\1/', $text, $matches) > 0) {
                    $primary['recordsets'] = array_values(array_unique(array_merge($primary['recordsets'], $matches[2])));
                }
            }
            $all = $this->declarations($patches, $constants);
            $owners[$name] = [
                'recordsets' => array_values(array_unique(array_merge($primary['recordsets'], $all['recordsets']))),
                'tables' => array_values(array_unique(array_merge($primary['tables'], $all['tables']))),
                'primary_recordsets' => $primary['recordsets'],
                'primary_tables' => $primary['tables'],
            ];
        }

        return $owners;
    }

    /**
     * @param  array<string, string>  $constants
     * @return array{recordsets: list<string>, tables: list<string>}
     */
    private function declarations(string $code, array $constants): array
    {
        $recordsets = [];
        if (preg_match_all('/install_new_recordset\s*\(\s*([\'"])([A-Za-z0-9_]+)\1/', $code, $matches) > 0) {
            $recordsets = $matches[2];
        }
        $tables = [];
        if (preg_match_all('/CreateTable\s*\(\s*([\'"])([A-Za-z0-9_]+)\1/', $code, $matches) > 0) {
            $tables = $matches[2];
        }
        if (preg_match_all('/CreateTable\s*\(\s*[A-Za-z_][A-Za-z0-9_]*::([A-Za-z_][A-Za-z0-9_]*)\s*,/', $code, $matches) > 0) {
            foreach ($matches[1] as $constant) {
                if (isset($constants[$constant])) {
                    $tables[] = $constants[$constant];
                }
            }
        }

        return ['recordsets' => array_values(array_unique($recordsets)), 'tables' => array_values(array_unique($tables))];
    }

    /** Source text without comments, or null when it cannot be read safely. */
    private function readSource(string $file): ?string
    {
        if (! is_file($file) || is_link($file) || ! is_readable($file)) {
            return null;
        }
        $source = (string) file_get_contents($file, false, null, 0, self::MAX_SOURCE_BYTES);
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
                    $code .= $token[1];
                }
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    /** @return array<string, string> `const name = 'value'` declarations in the module's own top-level files */
    private function constants(string $directory): array
    {
        $found = [];
        foreach (glob($directory.'/*.php') ?: [] as $file) {
            $text = $this->readSource($file);
            if ($text !== null && preg_match_all('/\bconst\s+([A-Za-z_][A-Za-z0-9_]*)\s*=\s*([\'"])([A-Za-z0-9_]+)\2/', $text, $matches, PREG_SET_ORDER) > 0) {
                foreach ($matches as $match) {
                    $found[$match[1]] = $match[3];
                }
            }
        }

        return $found;
    }

    /** The registered recordset a companion table belongs to, or the table itself when it is none. */
    private function recordsetOf(string $table, array $registered): string
    {
        foreach (self::RECORDSET_SUFFIXES as $suffix) {
            if (str_ends_with($table, '_'.$suffix) && isset($registered[substr($table, 0, -strlen($suffix) - 1)])) {
                return substr($table, 0, -strlen($suffix) - 1);
            }
        }

        return $table;
    }

    /** @return list<string> */
    private function column(PDO $pdo, string $table, string $column): array
    {
        return array_values(array_filter(array_map('strval', $pdo->query('SELECT `'.$column.'` FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN))));
    }

    private function kind(string $name): string
    {
        $lower = strtolower($name);
        if ($lower === 'base') {
            return 'Core Epesi';
        }
        if (str_starts_with($lower, 'premium_')) {
            return 'Premium';
        }
        if (str_starts_with($lower, 'custom_')) {
            return 'Custom';
        }
        foreach (self::CORE_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return 'Core Epesi';
            }
        }

        return 'Other';
    }
}
