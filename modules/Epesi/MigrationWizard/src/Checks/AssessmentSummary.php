<?php

namespace Epesi\Modules\MigrationWizard\Checks;

/**
 * Short, human-readable view of a legacy assessment: the installed modules that own data
 * (recordsets and plain tables) with their record and row counts, never a table-by-table
 * list. Pure (no database): it reads the assessment array built by LegacyInstallation
 * (its `module_data` section comes from LegacyModuleData, which explains how a module's
 * recordsets and tables are found and counted), MigrationDryRun and
 * MigrationExecutionAdvisor. The full report stays available through save().
 */
final class AssessmentSummary
{
    /**
     * Modules the page does not list in either group (user decision: nothing to show about them). A display
     * decision only: they stay in the saved report and their tables are handled by the usual keep/drop rules.
     */
    public const HIDDEN_MODULES = [
        'Utils_Watchdog', 'Base_Dashboard', 'Base_Acl',
        'Base', 'Base_Admin', 'Base_HomePage', 'Base_Notify', 'Base_Setup', 'Base_User_Settings', 'CRM_Calendar',
        'Premium_ExchangeRate', 'Premium_Import', 'Premium_PrintTemplates',
        'Tools_WhoIsOnline', 'Utils_BBCode', 'Utils_Messenger', 'Utils_RecordBrowser',
    ];

    public function summarize(array $report): array
    {
        $coverage = is_array($report['coverage'] ?? null) ? $report['coverage'] : [];
        $signature = (bool) ($report['legacy_signature'] ?? false);
        $compatible = (bool) ($report['compatible'] ?? false);
        $data = is_array($report['module_data'] ?? null) ? $report['module_data'] : null;

        $coverageByName = [];
        foreach ($coverage['modules'] ?? [] as $module) {
            if (is_array($module) && is_string($module['legacy_module'] ?? null)) {
                $coverageByName[$module['legacy_module']] = $module;
            }
        }

        $modules = [];
        foreach ($data['modules'] ?? [] as $module) {
            if (in_array((string) $module['name'], self::HIDDEN_MODULES, true)) {
                continue;
            }
            $kind = (string) ($module['kind'] ?? 'Other');
            $modules[] = [
                'name' => (string) $module['name'],
                'version' => (string) ($module['version'] ?? ''),
                'kind' => $kind,
                'recordsets' => array_values($module['recordsets'] ?? []),
                'tables' => array_values($module['tables'] ?? []),
                'action' => $this->action($coverageByName[$module['name']] ?? [], $kind, $compatible),
            ];
        }

        $advice = is_array($report['execution_advice'] ?? null) ? $report['execution_advice'] : [];

        return [
            'status' => 'assessed',
            'legacy_signature' => $signature,
            'fingerprint' => $report['fingerprint'] ?? null,
            'version' => (string) ($report['version'] ?? 'unknown'),
            'table_count' => (int) ($report['table_count'] ?? 0),
            'approved' => $compatible,
            'blocking' => $this->blocking($report, $signature, $compatible),
            'cli_recommended' => (bool) ($advice['cli_recommended'] ?? false),
            'size' => $advice === [] ? null : sprintf(
                'About %s of database data in %s estimated rows; %s files (%s) in the data folder%s.',
                $this->bytes((int) ($advice['database_estimated_bytes'] ?? 0)),
                number_format((int) ($advice['database_estimated_rows'] ?? 0)),
                number_format((int) ($advice['files_scanned'] ?? 0)),
                $this->bytes((int) ($advice['file_bytes_scanned'] ?? 0)),
                ($advice['file_scan_incomplete'] ?? false) ? ' (file scan incomplete)' : '',
            ),
            'custom_fields' => $this->customFields($coverage),
            'module_data_available' => $data !== null,
            'modules' => $modules,
            'module_count' => count($modules),
            'recordset_count' => array_sum(array_map(static fn (array $module): int => count($module['recordsets']), $modules)),
            'accounted_tables' => (int) ($data['accounted_tables'] ?? 0),
            'unaccounted_tables' => (int) ($data['unaccounted_tables'] ?? 0),
        ];
    }

    /** The summary as escaped HTML for the browser page (the version line is the page's own). */
    public function html(array $summary): string
    {
        if (($summary['status'] ?? null) !== 'assessed') {
            return '<p>'.e((string) ($summary['message'] ?? 'No assessment.')).'</p>';
        }
        // Inline styles on purpose: the setup panel's compiled CSS does not contain arbitrary utility classes.
        // Every block wraps inside the window (overflow-wrap) and has its own space below.
        $p = 'margin:0 0 1rem;overflow-wrap:anywhere;';
        $out = '<p style="'.$p.'"><strong>'.($summary['approved'] ? 'Data can be migrated.' : 'NOT approved: the migration cannot run on this installation.').'</strong></p>';
        // Order: verdict, command-line advice, why anything cannot be migrated, size, then the modules in two
        // groups (the custom fields to add belong to the first), the summary and the report links.
        $out .= '<p style="'.$p.'">'.($summary['cli_recommended']
            ? 'WARNING: Large installation detected. Browser operations may time out; running the migration from the command line (php artisan epesi:migrate-legacy) is recommended. If the browser stops responding or times out, reopen this page and press Start migration again: the migration continues from where it stopped.'
            : 'For long-running migrations, the command line is preferable even if the size estimate is small.').'</p>';
        foreach ($summary['blocking'] as $reason) {
            $out .= '<p style="'.$p.'">'.e($reason).'</p>';
        }
        if ($summary['size'] !== null) {
            $out .= '<p style="'.$p.'">'.e($summary['size']).'</p>';
        }

        if (! ($summary['module_data_available'] ?? true)) {
            $out .= '<p style="'.$p.'">The module list could not be built for this installation.</p>';
            $out .= $this->customFieldsHtml($summary['custom_fields'] ?? [], $p);
        } else {
            [$can, $cannot] = $this->groups($summary);
            $out .= '<p style="'.$p.'">Modules with data ('.(int) $summary['module_count'].'): recordsets show active records, tables show rows; ~ means estimated.</p>';
            $out .= '<h3 style="'.$p.'font-weight:700">Can be migrated ('.count($can).')</h3>'.$this->moduleTable($can, 'No module with data can be migrated.');
            $out .= $this->customFieldsHtml($summary['custom_fields'] ?? [], $p);
            $out .= '<h3 style="'.$p.'font-weight:700">Will not be migrated ('.count($cannot).')</h3>'
                .'<p style="'.$p.'">'.e(self::NOT_MIGRATED_NOTE).'</p>'
                .$this->moduleTable($cannot, 'Nothing.');
            $out .= '<h3 style="'.$p.'font-weight:700">Summary</h3><p style="'.$p.'">'.e($this->summaryLine($summary)).'</p>';
        }
        $links = $summary['report_links'] ?? null;
        if (is_array($links) && isset($links['html'], $links['txt'])) {
            $out .= '<p style="'.$p.'">Full report for diagnostics: <a class="fi-link underline" href="'.e($links['html']).'" target="_blank" rel="noopener">open the HTML report in a new window</a>'
                .' or <a class="fi-link underline" href="'.e($links['txt']).'" target="_blank" rel="noopener">the text (txt) report</a>.</p>';
        }

        return $out;
    }

    private const NOT_MIGRATED_NOTE = 'A Premium or Custom module is migrated only when a corresponding module is found; if none is, the module and its data are not migrated and stay untouched, as does every module without an importer yet (the reason for every module below: no importer reads its data).';

    /** @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>} the modules that can and cannot be migrated */
    private function groups(array $summary): array
    {
        $can = array_values(array_filter($summary['modules'], static fn (array $row): bool => $row['action'] === 'Migrated'));
        $cannot = array_values(array_filter($summary['modules'], static fn (array $row): bool => $row['action'] !== 'Migrated'));

        return [$can, $cannot];
    }

    private function summaryLine(array $summary): string
    {
        return number_format((int) $summary['accounted_tables']).' of '.number_format((int) $summary['table_count']).' database tables belong to the modules above or to system modules that are not listed (settings, search index, dashboard, access rights and the like); '
            .number_format((int) $summary['unaccounted_tables']).' belong to no listed module and are kept untouched.';
    }

    /** The same report as plain text (the first part of the txt diagnostic report). */
    public function text(array $summary): string
    {
        if (($summary['status'] ?? null) !== 'assessed') {
            return (string) ($summary['message'] ?? 'No assessment.')."\n";
        }
        $lines = ['epesi migration wizard: assessment', 'Legacy Epesi version: '.($summary['version'] ?? 'unknown'), '',
            $summary['approved'] ? 'Data can be migrated.' : 'NOT approved: the migration cannot run on this installation.',
            $summary['cli_recommended']
                ? 'WARNING: Large installation detected. Browser operations may time out; running the migration from the command line (php artisan epesi:migrate-legacy) is recommended. If the browser stops responding or times out, reopen this page and press Start migration again: the migration continues from where it stopped.'
                : 'For long-running migrations, the command line is preferable even if the size estimate is small.'];
        foreach ($summary['blocking'] as $reason) {
            $lines[] = $reason;
        }
        if ($summary['size'] !== null) {
            $lines[] = $summary['size'];
        }
        if (! ($summary['module_data_available'] ?? true)) {
            return implode("\n", $lines)."\nThe module list could not be built for this installation.\n";
        }
        [$can, $cannot] = $this->groups($summary);
        $lines[] = '';
        $lines[] = 'CAN BE MIGRATED ('.count($can).')';
        foreach ($can as $row) {
            $lines[] = '  '.$row['name'].' ['.$row['kind'].']: '.$this->dataLine($row);
        }
        $fields = $this->customFieldLines($summary['custom_fields'] ?? []);
        if ($fields['added'] !== [] || $fields['pending'] !== []) {
            $lines[] = '';
            $lines[] = 'Fields that will be added during migration:';
            foreach ([...$fields['added'], ...$fields['pending']] as $line) {
                $lines[] = '  - '.$line;
            }
        }
        $lines[] = '';
        $lines[] = 'WILL NOT BE MIGRATED ('.count($cannot).')';
        $lines[] = self::NOT_MIGRATED_NOTE;
        foreach ($cannot as $row) {
            $lines[] = '  '.$row['name'].' ['.$row['kind'].']: '.$this->dataLine($row);
        }
        $lines[] = '';
        $lines[] = 'SUMMARY';
        $lines[] = $this->summaryLine($summary);

        return implode("\n", $lines)."\n";
    }

    /** A standalone HTML page of the same report (the full assessment data is in the json and txt files). */
    public function document(array $summary): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>epesi migration wizard: assessment report</title>'
            .'<style>body{font-family:system-ui,sans-serif;max-width:60rem;margin:2rem auto;padding:0 1rem;line-height:1.5}</style>'
            .'</head><body><h1>epesi migration wizard: assessment report</h1><p>Legacy Epesi version: <strong>'.e((string) ($summary['version'] ?? 'unknown')).'</strong></p>'
            .$this->html($summary)
            .'</body></html>';
    }

    /** @param list<array<string, mixed>> $rows */
    private function moduleTable(array $rows, string $empty): string
    {
        if ($rows === []) {
            return '<p style="margin:0 0 1rem">'.e($empty).'</p>';
        }
        // Inline styles on purpose: the setup panel's compiled CSS does not contain arbitrary utility classes.
        $cell = 'padding:.35rem .75rem;vertical-align:top;text-align:start;border-bottom:1px solid rgba(128,128,128,.25);overflow-wrap:anywhere;';
        $nowrap = $cell.'white-space:nowrap;';
        $out = '<div style="max-width:100%;overflow-x:auto;margin-bottom:1rem"><table style="width:100%;border-collapse:collapse;font-size:.875rem"><thead><tr>'
            .'<th style="'.$nowrap.'">Module</th><th style="'.$nowrap.'">Kind</th><th style="'.$cell.'">Data</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $out .= '<tr><td style="'.$nowrap.'">'.e($row['name']).'</td><td style="'.$nowrap.'">'.e($row['kind']).'</td><td style="'.$cell.'">'.e($this->dataLine($row)).'</td></tr>';
        }

        return $out.'</tbody></table></div>';
    }

    /** One compact line: `company 2,005, contact 4,468; 1 table, 10 rows`. */
    private function dataLine(array $row): string
    {
        $recordsets = array_map(
            static fn (array $set): string => $set['tab'].' '.(($set['exact'] ?? true) ? '' : '~').number_format((int) $set['records']),
            $row['recordsets'],
        );
        // Plain tables are summed, never listed by name: "3 tables, 1,234 rows".
        $rows = 0;
        $estimated = false;
        foreach ($row['tables'] as $table) {
            $rows += (int) $table['rows'];
            $estimated = $estimated || ! ($table['exact'] ?? true);
        }
        $tables = $row['tables'] === [] ? '' : count($row['tables']).' '.(count($row['tables']) === 1 ? 'table' : 'tables').', '.($estimated ? '~' : '').number_format($rows).' rows';

        return implode('; ', array_filter([
            $recordsets === [] ? '' : implode(', ', $recordsets),
            $tables,
        ]));
    }

    /**
     * Write the full report for diagnostics, credential-like keys removed, as JSON, text and HTML side by side
     * in the directory (assessment.json|txt|html), and return the JSON path.
     */
    public function save(array $report, ?string $directory = null): string
    {
        $directory ??= storage_path('app/migration-wizard');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $base = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'assessment';
        $scrubbed = $this->scrub($report);
        $summary = $this->summarize($report);
        $json = json_encode($scrubbed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($base.'.json', $json, LOCK_EX);
        file_put_contents($base.'.txt', $this->text($summary)."\nFULL ASSESSMENT DATA (credentials removed)\n".$json."\n", LOCK_EX);
        file_put_contents($base.'.html', $this->document($summary), LOCK_EX);

        return $base.'.json';
    }

    private function scrub(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match('/pass|secret|token/i', $key) === 1) {
                unset($value[$key]);
            } elseif (is_array($item)) {
                $value[$key] = $this->scrub($item);
            }
        }

        return $value;
    }

    private function action(array $module, string $kind, bool $compatible): string
    {
        // Migrated when its importers exist (the app's own, run in place) and nothing blocks the module. A Premium or
        // Custom module counts only when an importer reads its data (importer_tabs, e.g. Premium_MultipleAddresses
        // through the contacts importer); every other one is never claimed here.
        if ($compatible && ($module['migrated_by_import'] ?? false) === true && (($kind !== 'Premium' && $kind !== 'Custom') || ($module['importer_tabs'] ?? []) !== [])) {
            return 'Migrated';
        }
        if ($compatible && ($module['disposition'] ?? null) === 'candidate_for_import' && ($module['outcome'] ?? null) === 'supported') {
            return 'Migrated';
        }
        if ($kind === 'Premium' || $kind === 'Custom') {
            return 'Kept untouched (not migrated)';
        }
        if (($module['schema_approved'] ?? null) === false && $compatible) {
            return 'Kept untouched (blocked, see above)';
        }

        return 'Kept untouched (not migrated yet)';
    }

    /** @return list<string> */
    private function blocking(array $report, bool $signature, bool $compatible): array
    {
        $reasons = [];
        if (! $signature) {
            $reasons[] = 'Required legacy tables are missing: this is not a recognised legacy Epesi database.';
        } elseif (! $compatible) {
            $reasons[] = 'The legacy release is not approved (the version must be 1.9.1 in both the database and the files); assessment only.';
        }
        foreach (is_array($report['blocked_modules'] ?? null) ? $report['blocked_modules'] : [] as $blocked) {
            $reasons[] = $this->blockedReason((string) ($blocked['legacy_module'] ?? ''), (array) ($blocked['schema_differences'] ?? []));
        }
        if (($report['file_inventory_incomplete'] ?? false) === true) {
            $reasons[] = 'The file inventory is incomplete.';
        }

        return $reasons;
    }

    /**
     * The custom fields the extra columns of the recordsets become, per recordset, from ModuleCoverage's
     * `custom_fields`: legacy field Account Manager (select) -> custom field "Account Manager", Contact link.
     * Fields whose data is not copied yet say why.
     *
     * @return list<array{recordset: string, table: string, fields: list<array<string, mixed>>}>
     */
    private function customFields(array $coverage): array
    {
        $out = [];
        foreach (is_array($coverage['custom_fields'] ?? null) ? $coverage['custom_fields'] : [] as $tab => $set) {
            $fields = [];
            foreach ((array) ($set['fields'] ?? []) as $field) {
                $fields[] = [
                    'legacy_name' => (string) ($field['legacy_name'] ?? ''),
                    'legacy_type' => (string) ($field['legacy_type'] ?? ''),
                    'declared' => (bool) ($field['declared'] ?? true),
                    'label' => (string) ($field['label'] ?? ''),
                    'type_label' => (string) ($field['type_label'] ?? ''),
                    'pending' => is_string($field['pending'] ?? null) ? $field['pending'] : null,
                ];
            }
            if ($fields !== []) {
                $out[] = ['recordset' => ucfirst(str_replace('_', ' ', (string) $tab)), 'table' => (string) ($set['table'] ?? ''), 'fields' => $fields];
            }
        }

        return $out;
    }

    /** @return array{added: list<string>, pending: list<string>} plain-text lines, one per custom field */
    private function customFieldLines(array $sets): array
    {
        $added = [];
        $pending = [];
        foreach ($sets as $set) {
            foreach ($set['fields'] as $field) {
                $line = $set['recordset'].': '.$field['legacy_name'].' (legacy '.$field['legacy_type'].($field['declared'] ? '' : ', a column no field declares').') → custom field ‘'.$field['label'].'’, '.$field['type_label'];
                if ($field['pending'] === null) {
                    $added[] = $line;
                } else {
                    $pending[] = $line.': the field is added, but its data is not copied yet ('.$field['pending'].'); the legacy table is kept.';
                }
            }
        }

        return ['added' => $added, 'pending' => $pending];
    }

    private function customFieldsHtml(array $sets, string $p): string
    {
        $lines = $this->customFieldLines($sets);
        if ($lines['added'] === [] && $lines['pending'] === []) {
            return '';
        }
        $list = static fn (array $items): string => '<ul style="'.$p.'padding-left:1.25rem;list-style:disc">'
            .implode('', array_map(static fn (string $line): string => '<li>'.e($line).'</li>', $items)).'</ul>';
        $out = '<h4 style="'.$p.'font-weight:700">Fields that will be added during migration</h4>'
            .'<p style="'.$p.'">Epesi 3 does not have these fields of the legacy recordsets (added by other modules or by custom changes). Each becomes a custom field and its data is migrated.</p>';
        if ($lines['added'] !== []) {
            $out .= $list($lines['added']);
        }
        if ($lines['pending'] !== []) {
            $out .= '<h4 style="'.$p.'font-weight:700">Fields whose data cannot be copied yet</h4>'.$list($lines['pending']);
        }

        return $out;
    }

    /** Why a module cannot be migrated, in words an administrator can act on; its data stays untouched. */
    private function blockedReason(string $module, array $differences): string
    {
        $parts = [];
        foreach ($differences as $difference) {
            $table = (string) ($difference['table'] ?? '');
            $list = static fn (string $key): string => implode(', ', array_map('strval', (array) ($difference[$key] ?? [])));
            $parts[] = match ($difference['problem'] ?? '') {
                'missing' => 'the table '.$table.' is missing',
                'no_approved_shape' => 'the table '.$table.' has no approved shape',
                'required_column_missing' => 'required column '.$list('missing').' is missing from the table '.$table,
                'too_many_extra_fields' => 'the table '.$table.' has '.(int) ($difference['count'] ?? 0).' extra fields, more than the '.(int) ($difference['max'] ?? 64).' custom fields one Epesi 3 recordset can hold',
                'version_not_approved' => 'the legacy release is not the supported one',
                default => 'the table '.$table.' differs from standard Epesi 1.9.1'
                    .($list('extra') !== '' ? ': extra column(s) '.$list('extra').' cannot be carried over by this module yet' : '')
                    .($list('missing') !== '' ? '; missing column(s) '.$list('missing') : ''),
            };
        }

        return $module.' cannot be migrated: '.($parts === [] ? 'its tables differ from standard Epesi 1.9.1' : implode('; ', $parts)).'. Its data is kept untouched.';
    }

    private function bytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return ($unit === 'B' ? (string) $bytes : number_format($bytes, 1)).' '.$unit;
            }
            $bytes /= 1024;
        }

        return '';
    }
}
