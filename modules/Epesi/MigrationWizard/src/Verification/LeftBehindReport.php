<?php

namespace Epesi\Modules\MigrationWizard\Verification;

/**
 * "What was left behind": everything a finished migration kept but did not migrate, in one
 * place, built from data the wizard already has (the coverage report, the verification report,
 * the exact rename map) plus a directory check for each Premium/Custom code folder. It scans
 * nothing else; the unreferenced files come from the verification report.
 *
 * Data nobody owns is not listed: it is removed right after verification (UnownedLegacyData).
 *
 * Per unmigrated module: its code folder (Premium/Custom code stays under modules/), its
 * `legacy_` tables and its data folders. Then the files in the migrated file areas that no
 * imported record references (they stay exactly where they were).
 */
final class LeftBehindReport
{
    public const NOTES = [
        'The old tables were renamed with a legacy_ prefix, so SQL written for the old installation does not run as written: add the prefix to the table names (or use the original name only after restoring the verified backup).',
        'legacy_id on migrated records links each of them to the row it came from in the old tables, which is how old rows can be matched to migrated records.',
    ];

    /**
     * @param  array<string, mixed>  $coverage  ModuleCoverage::report() output
     * @param  array<string, mixed>  $verification  MigrationVerifier::verify() output
     * @param  array<string, string>  $renameMap  original table => namespaced table
     * @param  string|null  $listFile  where the complete list of unreferenced files was written, if it was
     * @return array<string, mixed>
     */
    public function build(array $coverage, array $verification, array $renameMap, string $root, ?string $listFile = null): array
    {
        $tables = array_fill_keys(array_map('strval', (array) ($verification['preserved_tables'] ?? [])), true);
        $areas = array_fill_keys(array_map('strval', (array) ($verification['preserved_file_areas'] ?? [])), true);

        $owners = [];
        foreach ((array) ($coverage['tables'] ?? []) as $row) {
            $name = (string) ($row['table'] ?? '');
            if (isset($tables[$name])) {
                $owners[(string) ($row['legacy_module'] ?? '')]['tables'][] = ['table' => $name, 'legacy_table' => $renameMap[$name] ?? null, 'estimated_rows' => (int) ($row['estimated_rows'] ?? 0)];
            }
        }
        foreach ((array) ($coverage['files'] ?? []) as $row) {
            $name = (string) ($row['area'] ?? '');
            if (isset($areas[$name])) {
                $owners[(string) ($row['legacy_module'] ?? '')]['data_folders'][] = ['folder' => $name, 'files' => (int) ($row['count'] ?? 0), 'bytes' => (int) ($row['bytes'] ?? 0)];
            }
        }
        foreach ((array) ($coverage['modules'] ?? []) as $row) {
            $name = (string) ($row['legacy_module'] ?? '');
            if ($name !== '' && $this->codeFolder($root, $name) !== null) {
                $owners[$name] ??= [];
            }
        }

        // Data nobody owns (no module claims it) is removed right after verification, so it is never left behind.
        unset($owners['']);

        $modules = [];
        foreach ($owners as $name => $assets) {
            $modules[] = [
                'legacy_module' => $name === '' ? null : $name,
                'code_folder' => $name === '' ? null : $this->codeFolder($root, $name),
                'tables' => $this->sorted($assets['tables'] ?? [], 'table'),
                'data_folders' => $this->sorted($assets['data_folders'] ?? [], 'folder'),
            ];
        }
        usort($modules, static fn (array $a, array $b): int => strcmp((string) $a['legacy_module'], (string) $b['legacy_module']));

        $leftovers = (array) ($verification['leftover_files'] ?? []);

        return [
            'modules' => $modules,
            'unreferenced_files' => [
                'areas' => $leftovers,
                'total' => (array) ($verification['leftover_total'] ?? ['count' => 0, 'bytes' => 0]),
                'list_file' => $listFile,
            ],
            'notes' => self::NOTES,
        ];
    }

    /** modules/Premium/Warehouse for Premium_Warehouse (and Custom_*), when that directory exists: legacy core code is removed at cleanup, only these stay. */
    private function codeFolder(string $root, string $module): ?string
    {
        if (! str_starts_with($module, 'Premium_') && ! str_starts_with($module, 'Custom_')) {
            return null;
        }
        $relative = 'modules/'.str_replace('_', '/', $module);

        return is_dir(rtrim($root, '/\\').'/'.$relative) ? $relative : null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sorted(array $rows, string $key): array
    {
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a[$key], (string) $b[$key]));

        return $rows;
    }
}
