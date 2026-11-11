<?php

namespace Epesi\Modules\MigrationWizard\Detection;

use Epesi\Modules\MigrationWizard\Import\CertifiableImporter;
use Epesi\Modules\MigrationWizard\Import\LegacyImporter;

/**
 * Turns registered importers into the declarations ModuleCoverage::report()
 * takes. Fail closed: an importer that is not a CertifiableImporter, whose
 * legacy module is not in the catalog, that claims a table its legacy module
 * does not own, or that lacks required columns for a claimed table is left
 * out (and listed in $skipped), so its tables are never reported as supported.
 *
 * ModuleCoverage allows one importer per legacy module, so importers that share
 * one (Companies and Contacts both read CRM_Contacts) are merged into one
 * declaration: tables and columns are united, and the module is certified only
 * for a release every one of its importers is certified for.
 */
final class ImporterDeclarations
{
    /** @var array<string, string> importer id => why it was left out */
    public array $skipped = [];

    /**
     * @param  iterable<LegacyImporter>  $importers
     * @param  list<array<string, mixed>>  $knownModules  LegacyModuleCatalog::knownModules()
     * @return list<array<string, mixed>>
     */
    public function build(iterable $importers, array $knownModules): array
    {
        $this->skipped = [];
        $catalog = [];
        foreach ($knownModules as $module) {
            $catalog[(string) $module['legacy_module']] = $module;
        }

        /** @var array<string, list<CertifiableImporter>> $byModule */
        $byModule = [];
        foreach ($importers as $importer) {
            $reason = $this->incomplete($importer, $catalog);
            if ($reason !== null) {
                $this->skipped[$importer->id()] = $reason;

                continue;
            }
            /** @var CertifiableImporter $importer */
            $byModule[$importer->legacyModule()][] = $importer;
        }

        $declarations = [];
        foreach ($byModule as $module => $group) {
            $declarations[] = $this->merge((string) $module, $group, $catalog[$module]);
        }

        return $declarations;
    }

    private function incomplete(LegacyImporter $importer, array $catalog): ?string
    {
        if (! $importer instanceof CertifiableImporter) {
            return 'declares no legacy coverage';
        }
        $module = $importer->legacyModule();
        if ($module === '' || ! isset($catalog[$module])) {
            return 'legacy module is not in the catalog';
        }
        if ($importer->sourceFileAreas() !== [] && array_diff($importer->sourceFileAreas(), $catalog[$module]['source_file_areas']) !== []) {
            return "file area is not owned by {$module}";
        }
        $tables = $importer->sourceTables();
        $required = $importer->requiredColumns();
        $ignored = $importer->ignoredColumns();
        foreach ($tables as $table) {
            if (! in_array($table, $catalog[$module]['source_tables'], true)) {
                return "table {$table} is not owned by {$module}";
            }
            if (! is_array($required[$table] ?? null) || $required[$table] === []) {
                return "no required columns declared for {$table}";
            }
        }
        foreach ([$required, $ignored] as $declared) {
            foreach ($declared as $table => $columns) {
                if (! in_array((string) $table, $tables, true) || ! is_array($columns)) {
                    return 'columns declared for a table the importer does not claim';
                }
                foreach ($columns as $column) {
                    if (! is_string($column) || $column === '') {
                        return 'invalid column declaration';
                    }
                }
            }
        }
        foreach ($ignored as $table => $columns) {
            if (array_intersect($columns, $required[$table] ?? []) !== []) {
                return 'a column is both mapped and ignored';
            }
        }
        foreach ($importer->certifiedFor() as $entry) {
            if (! is_array($entry) || ! is_string($entry['version'] ?? null) || ! is_string($entry['fingerprint'] ?? null)
                || ! is_scalar($entry['module_version'] ?? null)) {
                return 'invalid certification entry';
            }
        }

        return null;
    }

    /**
     * @param  list<CertifiableImporter>  $group
     * @param  array<string, mixed>  $known
     * @return array<string, mixed>
     */
    private function merge(string $module, array $group, array $known): array
    {
        $tables = [];
        $areas = [];
        $required = [];
        $ignored = [];
        $certified = null;
        foreach ($group as $importer) {
            foreach ($importer->sourceTables() as $table) {
                $tables[$table] = true;
            }
            foreach ($importer->sourceFileAreas() as $area) {
                $areas[$area] = true;
            }
            foreach ($importer->requiredColumns() as $table => $columns) {
                $required[$table] = array_values(array_unique([...($required[$table] ?? []), ...$columns]));
            }
            foreach ($importer->ignoredColumns() as $table => $columns) {
                $ignored[$table] = array_values(array_unique([...($ignored[$table] ?? []), ...$columns]));
            }
            $entries = [];
            foreach ($importer->certifiedFor() as $entry) {
                $entries[json_encode([$entry['version'], $entry['fingerprint'], (string) $entry['module_version']])] = $entry;
            }
            $certified = $certified === null ? $entries : array_intersect_key($certified, $entries);
        }

        return [
            'id' => implode('+', array_map(static fn (LegacyImporter $i): string => $i->id(), $group)),
            'legacy_module' => $module,
            'destination_module' => $known['destination_module'],
            'source_tables' => array_keys($tables),
            'source_file_areas' => array_keys($areas),
            'required_columns' => $required,
            'ignored_columns' => $ignored,
            'certified_for' => array_values($certified ?? []),
        ];
    }
}
