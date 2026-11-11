<?php

namespace Epesi\Modules\MigrationWizard\Checks;

/**
 * Pure, read-only migration coverage summary from an assessment.
 * This is a preview, never authorization to mutate a database.
 *
 * Fail-closed: a table is only a candidate for import when the assessment's
 * ModuleCoverage report (the single classification path) marks it
 * `candidate_for_import`. An assessment without that report preserves everything.
 */
final class MigrationDryRun
{
    public function preview(array $assessment): array
    {
        $coverage = $assessment['coverage'] ?? null;
        $classified = [];
        foreach (is_array($coverage) ? ($coverage['tables'] ?? []) : [] as $table) {
            if (is_array($table) && is_string($table['table'] ?? null)) {
                $classified[$table['table']] = $table;
            }
        }
        $rows = [];
        $estimatedSupported = 0;
        $estimatedPreserved = 0;
        foreach ($assessment['table_statistics'] ?? [] as $entry) {
            $name = $entry['table'] ?? '';
            if (! is_string($name) || $name === '') {
                continue;
            }
            $count = max(0, (int) ($entry['estimated_rows'] ?? 0));
            $class = $classified[$name] ?? null;
            $covered = $class !== null && ($class['disposition'] ?? null) === 'candidate_for_import';
            $rows[] = [
                'table' => $name,
                'estimated_rows' => $count,
                'coverage_outcome' => $class['outcome'] ?? 'unknown',
                'unmapped_columns' => $class['unmapped_columns'] ?? [],
                'outcome' => $covered ? 'candidate_for_import' : 'preserve_unmapped',
            ];
            if ($covered) {
                $estimatedSupported += $count;
            } else {
                $estimatedPreserved += $count;
            }
        }

        return [
            'mode' => 'read_only_preview',
            'schema_fingerprint' => $assessment['fingerprint'] ?? null,
            'legacy_signature' => (bool) ($assessment['legacy_signature'] ?? false),
            'compatible' => (bool) ($assessment['compatible'] ?? false),
            'schema_approved' => (bool) (is_array($coverage) && ($coverage['schema_approved'] ?? false)),
            'rows_are_estimates' => true,
            'estimated_candidate_rows' => $estimatedSupported,
            'estimated_preserved_rows' => $estimatedPreserved,
            'unclassified_tables' => is_array($coverage) ? array_values($coverage['unclassified_tables'] ?? []) : [],
            'unclassified_file_areas' => is_array($coverage) ? array_values($coverage['unclassified_file_areas'] ?? []) : [],
            'tables' => $rows,
            'execution_allowed' => false,
            'warning' => 'Preview only: candidate tables require certified importers, field mappings and verification before migration.',
        ];
    }
}
