<?php

namespace Epesi\Modules\MigrationWizard\Report;

use Epesi\Modules\MigrationWizard\Import\ImportWarnings;
use Epesi\Modules\MigrationWizard\Import\InPlaceLegacyImport;

/**
 * The migration summary as one self-contained HTML page (written once at the end of the migration and opened in its
 * own window from the progress page): when it ran, which modules were imported by which importer and how many records, and, once verification has run, the
 * legacy tables with their source, imported and skipped counts. Built from the migration status alone; it reads
 * nothing else and never contains a secret.
 */
final class MigrationReport
{
    /** A module with thousands of warnings (an attachment file missing from the legacy data, for each mail) lists this many. */
    private const LOG_WARNINGS_PER_MODULE = 300;

    public static function path(): string
    {
        return storage_path('app/migration-wizard/migration-report.html');
    }

    public static function logPath(): string
    {
        return storage_path('app/migration-wizard/migration_log.txt');
    }

    public static function exists(): bool
    {
        return is_file(self::path());
    }

    /**
     * Written once, when the migration ends (verification passed): the page links to this file and never
     * regenerates it. A failure to write it never affects the migration.
     *
     * @param  array<string, mixed>  $status  MigrationRunner::status()
     */
    public function write(array $status): void
    {
        try {
            @file_put_contents(self::path(), $this->html($status), LOCK_EX);
            @file_put_contents(self::logPath(), $this->text($status), LOCK_EX);
        } catch (\Throwable) {
        }
    }

    /**
     * @param  array<string, mixed>  $status  MigrationRunner::status()
     */
    public function html(array $status): string
    {
        $results = is_array($status['import_results'] ?? null) ? $status['import_results'] : [];
        $completed = array_values(array_map('strval', (array) ($status['completed_importers'] ?? [])));
        $started = MigrationTimes::local($status['import_started_at'] ?? null);
        $finished = MigrationTimes::local($status['import_finished_at'] ?? null);
        $duration = MigrationTimes::elapsed($status['import_started_at'] ?? null, $status['import_finished_at'] ?? null);

        $facts = [['Import started', $started ?? 'not recorded']];
        $facts[] = $finished === null ? ['Data migration', 'still running or not finished'] : ['Data migration finished', $finished];
        if ($duration !== null) {
            $facts[] = ['Migration completed in', $duration];
        }
        if (($status['verification_outcome'] ?? null) !== null) {
            $facts[] = ['Verification', (string) $status['verification_outcome']];
        }

        $modules = '';
        $totals = ['created' => 0, 'updated' => 0, 'history_rows' => 0];
        foreach ($completed as $tab) {
            $row = is_array($results[$tab] ?? null) ? $results[$tab] : [];
            foreach ($totals as $key => $_) {
                $totals[$key] += (int) ($row[$key] ?? 0);
            }
            $modules .= '<tr><td>'.e(implode(', ', InPlaceLegacyImport::LEGACY_MODULES[$tab] ?? [])).'</td><td>'.e($tab).'</td>'
                .$this->numbers([(int) ($row['created'] ?? 0), (int) ($row['updated'] ?? 0), (int) ($row['history_rows'] ?? 0)]).'</tr>';
        }
        $modules .= '<tr class="total"><td colspan="2">Total</td>'.$this->numbers(array_values($totals)).'</tr>';

        $tables = '';
        foreach ((array) ($status['table_certification'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $tables .= '<tr><td>'.e((string) ($entry['table'] ?? '')).'</td><td>'.e((string) ($entry['label'] ?? '')).'</td>'
                .$this->numbers([(int) ($entry['source'] ?? 0), (int) ($entry['imported'] ?? 0), (int) ($entry['skipped'] ?? 0)])
                .'<td>'.e($this->tableOutcome($entry)).'</td></tr>';
        }

        $out = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>Migration report</title><style>'
            .'body{font-family:system-ui,sans-serif;margin:2rem auto;max-width:60rem;padding:0 1rem;color:#1f2937;background:#fff}'
            .'h1{font-size:1.5rem}h2{font-size:1.15rem;margin-top:2rem}table{border-collapse:collapse;width:100%;margin:.5rem 0}'
            .'th,td{border:1px solid #d1d5db;padding:.35rem .6rem;text-align:left}td.n,th.n{text-align:right}th{background:#f3f4f6}tr.total td{font-weight:700;background:#f9fafb}'
            .'@media (prefers-color-scheme:dark){body{background:#111827;color:#e5e7eb}th{background:#1f2937}tr.total td{background:#1f2937}th,td{border-color:#374151}}'
            .'</style></head><body><h1>Epesi migration report</h1><table>';
        foreach ($facts as [$label, $value]) {
            $out .= '<tr><th>'.e($label).'</th><td>'.e($value).'</td></tr>';
        }
        $out .= '</table><h2>Modules imported</h2>'
            .($completed === [] ? '<p>No importer has finished yet.</p>' : '<table><tr><th>Legacy module</th><th>Importer</th><th class="n">Created</th><th class="n">Updated</th><th class="n">History rows</th></tr>'.$modules.'</table>')
            .'<p>Updated: the record or list item already existed in the new Epesi (for example the standard lists a new installation holds) and was set to the legacy value. The warnings are in the detailed log, migration_log.txt.</p>'
            .'<h2>Tables</h2>'
            .($tables === '' ? '<p>The table-by-table counts are added when verification has run.</p>' : '<table><tr><th>Legacy table</th><th>Records</th><th class="n">Source</th><th class="n">Imported</th><th class="n">Skipped</th><th>Result</th></tr>'.$tables.'</table>')
            .'</body></html>';

        return $out;
    }

    /**
     * The detailed migration log: a summary of the run, the modules and tables, and at the end the warnings per
     * module (each importer's own messages, as it wrote them).
     *
     * @param  array<string, mixed>  $status  MigrationRunner::status()
     */
    public function text(array $status): string
    {
        $results = is_array($status['import_results'] ?? null) ? $status['import_results'] : [];
        $completed = array_values(array_map('strval', (array) ($status['completed_importers'] ?? [])));
        $line = static fn (string $label, ?string $value): string => str_pad($label.':', 28).($value ?? 'not recorded')."\n";
        $out = "EPESI MIGRATION LOG\n===================\n\n";
        $out .= $line('Import started', MigrationTimes::local($status['import_started_at'] ?? null));
        $out .= $line('Data migration finished', MigrationTimes::local($status['import_finished_at'] ?? null));
        $out .= $line('Migration completed in', MigrationTimes::elapsed($status['import_started_at'] ?? null, $status['import_finished_at'] ?? null));
        $out .= $line('Verification', isset($status['verification_outcome']) ? (string) $status['verification_outcome'] : null);

        $totals = ['created' => 0, 'updated' => 0, 'history_rows' => 0];
        $warningCount = 0;
        $modules = '';
        $warnings = '';
        foreach ($completed as $tab) {
            $row = is_array($results[$tab] ?? null) ? $results[$tab] : [];
            foreach ($totals as $key => $_) {
                $totals[$key] += (int) ($row[$key] ?? 0);
            }
            $name = implode(', ', InPlaceLegacyImport::LEGACY_MODULES[$tab] ?? []) ?: $tab;
            $modules .= '  '.str_pad($name, 52).str_pad($tab, 18).str_pad(number_format((int) ($row['created'] ?? 0)), 10, ' ', STR_PAD_LEFT).str_pad(number_format((int) ($row['updated'] ?? 0)), 10, ' ', STR_PAD_LEFT).str_pad(number_format((int) ($row['history_rows'] ?? 0)), 14, ' ', STR_PAD_LEFT)."\n";
            $messages = ImportWarnings::read($tab);
            $warningCount += count($messages);
            if ($messages !== []) {
                $warnings .= "\n--- {$name} ({$tab}): ".count($messages)." warning(s)\n";
                foreach (array_slice($messages, 0, self::LOG_WARNINGS_PER_MODULE) as $message) {
                    $warnings .= "  - {$message}\n";
                }
                if (count($messages) > self::LOG_WARNINGS_PER_MODULE) {
                    $warnings .= '  ... and '.number_format(count($messages) - self::LOG_WARNINGS_PER_MODULE).' more; the complete list is in storage/app/migration-wizard/warnings/'.$tab.".txt\n";
                }
            }
        }
        $out .= $line('Records created', number_format($totals['created']));
        $out .= $line('Records updated', number_format($totals['updated']));
        $out .= $line('History rows', number_format($totals['history_rows']));
        $out .= $line('Warnings', number_format($warningCount).' (listed per module at the end)');

        $out .= "\nMODULES IMPORTED\n----------------\n  ".str_pad('Legacy module', 52).str_pad('Importer', 18).str_pad('Created', 10, ' ', STR_PAD_LEFT).str_pad('Updated', 10, ' ', STR_PAD_LEFT).str_pad('History rows', 14, ' ', STR_PAD_LEFT)."\n".$modules;
        $out .= "\n  Updated = the record or list item already existed in the new Epesi (for example the standard lists a new\n  installation holds) and was set to the legacy value.\n";

        $out .= "\nTABLES\n------\n";
        $tables = array_values(array_filter((array) ($status['table_certification'] ?? []), 'is_array'));
        if ($tables === []) {
            $out .= "  The table-by-table counts are added when verification has run.\n";
        }
        foreach ($tables as $entry) {
            $out .= '  '.str_pad((string) ($entry['table'] ?? ''), 40).'source '.(int) ($entry['source'] ?? 0).' = imported '.(int) ($entry['imported'] ?? 0).' + skipped '.(int) ($entry['skipped'] ?? 0).'  '.$this->tableOutcome($entry)."\n";
        }

        $out .= "\nWARNINGS PER MODULE\n-------------------\n";
        $out .= $warnings === '' ? "  None.\n" : $warnings;

        return $out;
    }

    /** @param  array<string, mixed>  $entry */
    private function tableOutcome(array $entry): string
    {
        if (($entry['status'] ?? '') === 'certified') {
            return 'all accounted for';
        }
        $pending = array_map('strval', (array) ($entry['pending'] ?? []));

        return $pending === [] ? 'kept, counts differ' : 'kept: '.implode('; ', $pending);
    }

    /** @param  list<int>  $values */
    private function numbers(array $values): string
    {
        return implode('', array_map(static fn (int $value): string => '<td class="n">'.number_format($value).'</td>', $values));
    }
}
