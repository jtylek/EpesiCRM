<?php

namespace Epesi\Modules\MigrationWizard\Detection;

use App\Services\LegacyImport\LegacyCustomFieldPlanner;
use App\Services\LegacyImport\LegacyStockColumns;
use InvalidArgumentException;

/**
 * Read-only legacy data coverage. Exact ownership declarations are required;
 * prefixes and directory names never prove that data may be imported or removed.
 */
final class ModuleCoverage
{
    /**
     * $inventory contains version, fingerprint, modules (name/version rows),
     * tables (name => columns/estimated_rows/estimated_bytes), and files
     * (area => count/bytes). Importers and approved schemas are explicit inputs.
     *
     * An importer declares id, legacy_module, destination_module, source_tables,
     * source_file_areas, required_columns (table => mapped columns),
     * ignored_columns (table => columns deliberately left behind) and
     * certified_for (list of version/fingerprint/module_version it was certified against).
     * A table is supported only when every one of its columns is mapped or ignored.
     *
     * Approval is per legacy module, never for the whole database. An approved
     * schema is ['version' => ..., 'modules' => [module => list of accepted
     * variants (table => SchemaFingerprint::tableHash)]] (ApprovedLegacySchemas).
     * `schema_approved` only says the release is approved (fail closed: an
     * unknown version, or one that is not the same from both sources, is not).
     * Whether a module's own tables match that release is `modules[].schema_approved`;
     * a mismatch blocks that module only (its tables and files are preserved and
     * it is listed in `blocked_modules`). Premium, Custom and unknown modules and
     * tables never make the installation unapproved.
     */
    public function report(array $inventory, array $knownModules = [], array $importers = [], array $approvedSchemas = [], array $inPlaceImporters = []): array
    {
        $columns = $this->validateInventory($inventory);
        $approved = $this->isApproved($inventory, $approvedSchemas);
        $registered = $this->registeredModules($inventory['modules']);
        [$known, $tableOwners, $fileOwners] = $this->knownModules($knownModules, $registered);
        $byModule = $this->importers($importers, $known);
        $customFields = $this->customFields($inventory, $columns, $tableOwners);
        $schemas = $this->moduleSchemas($inventory, $columns, $known, $registered, $approvedSchemas, $approved, $customFields);

        $certified = [];
        foreach ($byModule as $name => $importer) {
            $certified[$name] = $approved && isset($registered[$name]) && ($schemas[$name]['approved'] ?? false)
                && $this->isCertified($importer['certified_for'] ?? [], $inventory, $registered[$name], $schemas[$name]['fingerprint']);
        }

        $tables = $this->classifyTables($inventory['tables'], $columns, $tableOwners, $byModule, $certified);
        $files = $this->classifyFiles($inventory['files'], $fileOwners, $byModule, $certified);
        $modules = $this->classifyModules($registered, $known, $byModule, $certified, $tables, $files, $schemas, $inPlaceImporters);
        // "Can be migrated": a registered importer reads this module's data in the in-place run, the release is the supported
        // one, and nothing blocks the module (a module the catalog does not describe has no shape to check yet).
        foreach ($modules as &$module) {
            $module['migrated_by_import'] = $approved && $module['importer_tabs'] !== [] && $module['schema_approved'] !== false;
        }
        unset($module);

        return [
            'version' => $inventory['version'],
            'schema_fingerprint' => $inventory['fingerprint'],
            'schema_approved' => $approved,
            'version_approved' => $approved,
            'assessment_only' => ! $approved,
            'blocked_modules' => array_values(array_map(
                static fn (array $module): array => ['legacy_module' => $module['legacy_module'], 'schema_differences' => $module['schema_differences']],
                array_filter($modules, static fn (array $module): bool => $module['schema_approved'] === false),
            )),
            'modules' => $modules,
            'custom_fields' => $customFields,
            'tables' => array_values($tables),
            'files' => array_values($files),
            'unclassified_tables' => array_column(array_filter($tables, static fn (array $row): bool => $row['outcome'] === 'unknown'), 'table'),
            'unclassified_file_areas' => array_column(array_filter($files, static fn (array $row): bool => $row['outcome'] === 'unknown'), 'area'),
            'import_coverage_verified' => false,
        ];
    }

    /** @return array<string, list<string>> table => columns */
    private function validateInventory(array $inventory): array
    {
        foreach (['version', 'fingerprint', 'modules', 'tables', 'files'] as $key) {
            if (! array_key_exists($key, $inventory)) {
                throw new InvalidArgumentException('Incomplete legacy coverage inventory.');
            }
        }
        if (! is_string($inventory['version']) || ! is_string($inventory['fingerprint'])
            || ! preg_match('/^[a-f0-9]{64}$/D', $inventory['fingerprint'])
            || ! is_array($inventory['modules']) || ! is_array($inventory['tables']) || ! is_array($inventory['files'])) {
            throw new InvalidArgumentException('Invalid legacy version or schema fingerprint.');
        }
        $columns = [];
        foreach ($inventory['tables'] as $table => $details) {
            $table = (string) $table;
            if ($table === '' || ! is_array($details['columns'] ?? null)) {
                throw new InvalidArgumentException('Invalid legacy table inventory.');
            }
            $columns[$table] = array_values(array_unique(array_map('strval', $details['columns'])));
        }
        if (! hash_equals((new SchemaFingerprint)->hash($columns), $inventory['fingerprint'])) {
            throw new InvalidArgumentException('Legacy schema fingerprint does not match the inventory.');
        }

        return $columns;
    }

    /** The release is approved (an exact version in the list; 'unknown' never is). Not a statement about any module's tables. */
    private function isApproved(array $inventory, array $approvedSchemas): bool
    {
        foreach ($approvedSchemas as $schema) {
            if (is_array($schema) && is_string($schema['version'] ?? null) && $schema['version'] !== 'unknown'
                && $schema['version'] === $inventory['version'] && is_array($schema['modules'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /** SchemaFingerprint of just the tables a module owns (the ones present; a missing one changes it). */
    public static function moduleFingerprint(array $sourceTables, array $columns): string
    {
        $owned = [];
        foreach ($sourceTables as $table) {
            if (isset($columns[$table])) {
                $owned[$table] = $columns[$table];
            }
        }

        return (new SchemaFingerprint)->hash($owned);
    }

    /**
     * For every known module in the registry: do its own tables match an
     * approved variant of this release? Fail closed: no approved release, no
     * approved shape for the module, a missing table or any column difference
     * (an extra column on a Core table included) means not approved. Only that
     * module is affected.
     *
     * @return array<string, array{approved: bool, fingerprint: string, differences: list<array{table: string, problem: string}>}>
     */
    private function moduleSchemas(array $inventory, array $columns, array $known, array $registered, array $approvedSchemas, bool $versionApproved, array $customFields = []): array
    {
        $variantsByModule = [];
        foreach ($approvedSchemas as $schema) {
            if ($versionApproved && is_array($schema) && ($schema['version'] ?? null) === $inventory['version'] && is_array($schema['modules'] ?? null)) {
                foreach ($schema['modules'] as $module => $variants) {
                    foreach (is_array($variants) ? $variants : [] as $variant) {
                        if (is_array($variant)) {
                            $variantsByModule[(string) $module][] = $variant;
                        }
                    }
                }
            }
        }

        $schemas = [];
        foreach ($known as $name => $module) {
            if (! array_key_exists($name, $registered)) {
                continue;
            }
            $owned = array_map('strval', $module['source_tables'] ?? []);
            $best = null;
            foreach ($variantsByModule[$name] ?? [] as $variant) {
                $differences = [];
                foreach ($variant as $table => $hash) {
                    $table = (string) $table;
                    if (! isset($columns[$table])) {
                        $differences[] = ['table' => $table, 'problem' => 'missing'];
                    } elseif (! is_string($hash) || ! hash_equals($hash, SchemaFingerprint::tableHash($columns[$table]))) {
                        $difference = $this->tableDifference($table, $columns[$table], $customFields);
                        if ($difference !== null) {
                            $differences[] = $difference;
                        }
                    }
                }
                foreach (array_diff($owned, array_map('strval', array_keys($variant))) as $table) {
                    $differences[] = ['table' => $table, 'problem' => 'no_approved_shape'];
                }
                if ($best === null || count($differences) < count($best)) {
                    $best = $differences;
                }
            }
            if ($best === null) {
                $best = $versionApproved
                    ? array_map(static fn (string $table): array => ['table' => $table, 'problem' => 'no_approved_shape'], $owned)
                    : [['table' => '', 'problem' => 'version_not_approved']];
                if ($best === []) {
                    $best = [['table' => '', 'problem' => 'no_approved_shape']];
                }
            }
            $schemas[$name] = [
                'approved' => $versionApproved && $owned !== [] && $best === [],
                'fingerprint' => self::moduleFingerprint($owned, $columns),
                'differences' => $best,
            ];
        }

        return $schemas;
    }

    /**
     * What a table that is not the stock shape means for its module, or null when it does not block.
     *
     * A RecordBrowser data table whose importer carries custom fields (LegacyStockColumns::RECORDSET_TABLES)
     * is migratable with extra columns: each becomes a custom field. It is blocked only by a column the
     * importer cannot do without (LegacyStockColumns::REQUIRED) or by more extra fields than one model can
     * hold. Any other table that differs cannot carry its extras, so it blocks, naming the columns.
     *
     * @param  list<string>  $actual
     * @param  array<string, array{table: string, legacy_module: string, fields: list<array<string, mixed>>}>  $customFields
     * @return array<string, mixed>|null
     */
    private function tableDifference(string $table, array $actual, array $customFields): ?array
    {
        $stock = LegacyStockColumns::for($table);
        if (isset(LegacyStockColumns::RECORDSET_TABLES[$table])) {
            $missing = array_values(array_diff(LegacyStockColumns::REQUIRED, $actual));
            if ($missing !== []) {
                return ['table' => $table, 'problem' => 'required_column_missing', 'missing' => $missing];
            }
            $count = count($customFields[LegacyStockColumns::RECORDSET_TABLES[$table]]['fields'] ?? []);
            if ($count > LegacyCustomFieldPlanner::MAX_FIELDS) {
                return ['table' => $table, 'problem' => 'too_many_extra_fields', 'count' => $count, 'max' => LegacyCustomFieldPlanner::MAX_FIELDS];
            }

            return null;
        }

        return ['table' => $table, 'problem' => 'columns_differ']
            + ($stock === null ? [] : ['missing' => array_values(array_diff($stock, $actual)), 'extra' => array_values(array_diff($actual, $stock))]);
    }

    /**
     * The custom fields the extra columns of the recordset tables become (LegacyCustomFieldPlanner), for the
     * tables a registered module owns: tab => table, owning legacy module and fields. Empty when a table has no extras.
     *
     * @return array<string, array{table: string, legacy_module: string, fields: list<array<string, mixed>>}>
     */
    private function customFields(array $inventory, array $columns, array $tableOwners): array
    {
        $planner = new LegacyCustomFieldPlanner;
        $result = [];
        foreach (LegacyStockColumns::RECORDSET_TABLES as $table => $tab) {
            if (! isset($columns[$table], $tableOwners[$table])) {
                continue;
            }
            $info = is_array($inventory['record_fields'][$tab] ?? null) ? $inventory['record_fields'][$tab] : [];
            $fields = $planner->plan($tab, $columns[$table], (array) ($info['column_types'] ?? []), (array) ($info['fields'] ?? []), (array) ($info['callbacks'] ?? []));
            if ($fields !== []) {
                $result[$tab] = ['table' => $table, 'legacy_module' => $tableOwners[$table], 'fields' => $fields];
            }
        }

        return $result;
    }

    /** @return array<string, string> legacy module => registered version */
    private function registeredModules(array $rows): array
    {
        $registered = [];
        foreach ($rows as $row) {
            $name = is_array($row) ? ($row['name'] ?? null) : null;
            if (! is_string($name) || $name === '' || array_key_exists($name, $registered)
                || ! is_scalar($row['version'] ?? null)) {
                throw new InvalidArgumentException('Invalid or duplicate legacy module registry entry.');
            }
            $registered[$name] = (string) $row['version'];
        }

        return $registered;
    }

    /** Ownership is only taken by modules present in the legacy registry. */
    private function knownModules(array $knownModules, array $registered): array
    {
        $known = [];
        $tableOwners = [];
        $fileOwners = [];
        foreach ($knownModules as $module) {
            $name = $module['legacy_module'] ?? null;
            if (! is_string($name) || $name === '' || array_key_exists($name, $known)
                || ! is_string($module['destination_module'] ?? null)) {
                throw new InvalidArgumentException('Invalid or duplicate known module declaration.');
            }
            $known[$name] = $module;
            if (! array_key_exists($name, $registered)) {
                continue;
            }
            foreach ($module['source_tables'] ?? [] as $table) {
                if (! is_string($table) || $table === '' || array_key_exists($table, $tableOwners)) {
                    throw new InvalidArgumentException('Ambiguous table ownership.');
                }
                $tableOwners[$table] = $name;
            }
            foreach ($module['source_file_areas'] ?? [] as $area) {
                if (! is_string($area) || $area === '' || array_key_exists($area, $fileOwners)) {
                    throw new InvalidArgumentException('Ambiguous file ownership.');
                }
                $fileOwners[$area] = $name;
            }
        }

        return [$known, $tableOwners, $fileOwners];
    }

    /** @return array<string, array> legacy module => importer */
    private function importers(array $importers, array $known): array
    {
        $byModule = [];
        $importerIds = [];
        foreach ($importers as $importer) {
            $name = $importer['legacy_module'] ?? null;
            $id = $importer['id'] ?? null;
            if (! is_string($name) || ! array_key_exists($name, $known) || array_key_exists($name, $byModule)
                || ! is_string($id) || $id === '' || isset($importerIds[$id])
                || ($importer['destination_module'] ?? null) !== $known[$name]['destination_module']) {
                throw new InvalidArgumentException('Invalid or ambiguous importer declaration.');
            }
            foreach (['source_tables', 'source_file_areas'] as $key) {
                if (! is_array($importer[$key] ?? null)
                    || array_diff($importer[$key], $known[$name][$key] ?? []) !== []) {
                    throw new InvalidArgumentException('Importer claims assets outside its known module.');
                }
            }
            foreach (['required_columns', 'ignored_columns'] as $key) {
                if (! is_array($importer[$key] ?? [])
                    || array_diff(array_map('strval', array_keys($importer[$key] ?? [])), $importer['source_tables']) !== []) {
                    throw new InvalidArgumentException('Importer maps columns of a table it does not claim.');
                }
                foreach ($importer[$key] ?? [] as $list) {
                    if (! is_array($list)) {
                        throw new InvalidArgumentException('Invalid importer column declaration.');
                    }
                }
            }
            foreach ($importer['ignored_columns'] ?? [] as $table => $ignored) {
                if (array_intersect($ignored, $importer['required_columns'][$table] ?? []) !== []) {
                    throw new InvalidArgumentException('Importer both maps and ignores the same column.');
                }
            }
            if (! is_array($importer['certified_for'] ?? [])) {
                throw new InvalidArgumentException('Invalid importer certification.');
            }
            $importerIds[$id] = true;
            $byModule[$name] = $importer;
        }

        return $byModule;
    }

    /** Certification counts only for the exact Epesi version, module schema (its own tables) and module version it was made against. */
    private function isCertified(array $certifiedFor, array $inventory, string $moduleVersion, string $moduleFingerprint): bool
    {
        foreach ($certifiedFor as $entry) {
            if (is_array($entry)
                && ($entry['version'] ?? null) === $inventory['version']
                && ($entry['fingerprint'] ?? null) === $moduleFingerprint
                && is_scalar($entry['module_version'] ?? null)
                && (string) $entry['module_version'] === $moduleVersion) {
                return true;
            }
        }

        return false;
    }

    private function classifyTables(array $inventoryTables, array $columns, array $tableOwners, array $byModule, array $certified): array
    {
        $tables = [];
        foreach ($inventoryTables as $name => $details) {
            $name = (string) $name;
            $owner = $tableOwners[$name] ?? null;
            $importer = $owner === null ? null : ($byModule[$owner] ?? null);
            $claimed = $importer !== null && in_array($name, $importer['source_tables'], true);
            $required = $claimed ? array_map('strval', $importer['required_columns'][$name] ?? []) : [];
            $ignored = $claimed ? array_map('strval', $importer['ignored_columns'][$name] ?? []) : [];
            $missing = array_values(array_diff($required, $columns[$name]));
            $unmapped = array_values(array_diff($columns[$name], $required, $ignored));
            $covered = $claimed && ($certified[$owner] ?? false)
                && $required !== [] && $missing === [] && $unmapped === [];
            $tables[$name] = [
                'table' => $name,
                'columns' => $columns[$name],
                'unmapped_columns' => $unmapped,
                'missing_columns' => $missing,
                'estimated_rows' => max(0, (int) ($details['estimated_rows'] ?? 0)),
                'estimated_bytes' => max(0, (int) ($details['estimated_bytes'] ?? 0)),
                'legacy_module' => $owner,
                'importer' => $claimed ? $importer['id'] : null,
                'outcome' => $owner === null ? 'unknown' : ($covered ? 'supported' : 'known_but_not_migrated'),
                'disposition' => $covered ? 'candidate_for_import' : 'preserve',
            ];
        }
        ksort($tables, SORT_STRING);

        return $tables;
    }

    private function classifyFiles(array $inventoryFiles, array $fileOwners, array $byModule, array $certified): array
    {
        $files = [];
        foreach ($inventoryFiles as $area => $details) {
            $area = (string) $area;
            if ($area === '' || ! is_array($details)) {
                throw new InvalidArgumentException('Invalid legacy file inventory.');
            }
            $owner = $fileOwners[$area] ?? null;
            $importer = $owner === null ? null : ($byModule[$owner] ?? null);
            $claimed = $importer !== null && in_array($area, $importer['source_file_areas'], true);
            $covered = $claimed && ($certified[$owner] ?? false);
            $files[$area] = [
                'area' => $area,
                'count' => max(0, (int) ($details['count'] ?? 0)),
                'bytes' => max(0, (int) ($details['bytes'] ?? 0)),
                'legacy_module' => $owner,
                'importer' => $claimed ? $importer['id'] : null,
                'outcome' => $owner === null ? 'unknown' : ($covered ? 'supported' : 'known_but_not_migrated'),
                'disposition' => $covered ? 'candidate_for_import' : 'preserve',
            ];
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    private function classifyModules(array $registered, array $known, array $byModule, array $certified, array $tables, array $files, array $schemas = [], array $inPlaceImporters = []): array
    {
        $modules = [];
        foreach ($registered as $name => $version) {
            $name = (string) $name;
            $moduleTables = array_values(array_filter($tables, static fn (array $row): bool => $row['legacy_module'] === $name));
            $moduleFiles = array_values(array_filter($files, static fn (array $row): bool => $row['legacy_module'] === $name));
            $assets = array_merge($moduleTables, $moduleFiles);
            $knownModule = $known[$name] ?? null;
            $covered = $knownModule !== null && ($certified[$name] ?? false) && $assets !== [];
            foreach ($assets as $asset) {
                $covered = $covered && $asset['outcome'] === 'supported';
            }
            foreach ($knownModule['source_tables'] ?? [] as $table) {
                $covered = $covered && isset($tables[$table]);
            }
            foreach ($knownModule['source_file_areas'] ?? [] as $area) {
                $covered = $covered && isset($files[$area]);
            }
            $modules[] = [
                'legacy_module' => $name,
                'legacy_version' => $version,
                'destination_module' => $knownModule['destination_module'] ?? null,
                'tables' => array_column($moduleTables, 'table'),
                'file_areas' => array_column($moduleFiles, 'area'),
                'estimated_rows' => array_sum(array_column($moduleTables, 'estimated_rows')),
                'estimated_bytes' => array_sum(array_column($moduleTables, 'estimated_bytes')) + array_sum(array_column($moduleFiles, 'bytes')),
                'importer' => $byModule[$name]['id'] ?? null,
                'importer_certified' => $certified[$name] ?? false,
                'schema_approved' => isset($schemas[$name]) ? $schemas[$name]['approved'] : null,
                'schema_fingerprint' => $schemas[$name]['fingerprint'] ?? null,
                'schema_differences' => $schemas[$name]['differences'] ?? [],
                // Migrated by the app's own importers (import:legacy) run in place; set in report() once the release is known to be approved.
                'importer_tabs' => $inPlaceImporters[$name] ?? [],
                'migrated_by_import' => false,
                'outcome' => $knownModule === null ? 'unknown' : ($covered ? 'supported' : 'known_but_not_migrated'),
                'disposition' => $covered ? 'candidate_for_import' : 'preserve',
            ];
        }
        usort($modules, static fn (array $a, array $b): int => strcmp($a['legacy_module'], $b['legacy_module']));

        return $modules;
    }

    /** Existing assessment API remains conservative until certified metadata is wired in. */
    public function summarize(array $tables): array
    {
        $knownCore = ['variables', 'modules', 'user_login', 'user_password'];
        $unknown = array_values(array_diff($tables, $knownCore));
        sort($unknown, SORT_STRING);

        return [
            'known_signature_tables' => array_values(array_intersect($knownCore, $tables)),
            'unclassified_tables' => $unknown,
            'unsupported_count' => count($unknown),
            'import_coverage_verified' => false,
            'disposition' => 'Preserve all legacy tables pending importer mapping and review.',
        ];
    }
}
