<?php

namespace Epesi\Modules\MigrationWizard\Import;

use RuntimeException;

/**
 * Deterministic, non-mutating plan for registered legacy importers.
 * Rejects missing dependencies, duplicate IDs and dependency cycles.
 */
final class ImportPlan
{
    public function build(array $importers, array $availableTables): array
    {
        $byId = [];
        foreach ($importers as $importer) {
            $id = $importer['id'] ?? null;
            if (! is_string($id) || ! preg_match('/^[a-z][a-z0-9_-]*$/D', $id) || isset($byId[$id])) {
                throw new RuntimeException('Invalid or duplicate importer identifier.');
            }
            $byId[$id] = $importer;
        }
        $available = array_fill_keys($availableTables, true);
        $ordered = [];
        $visiting = [];
        $visited = [];
        $visit = function (string $id) use (&$visit, &$visiting, &$visited, &$ordered, $byId, $available): void {
            if (isset($visited[$id])) {
                return;
            }
            if (isset($visiting[$id])) {
                throw new RuntimeException('Importer dependency cycle detected.');
            }
            if (! isset($byId[$id])) {
                throw new RuntimeException('Importer dependency is not registered.');
            }
            $visiting[$id] = true;
            $item = $byId[$id];
            foreach ($item['depends_on'] ?? [] as $dependency) {
                if (! is_string($dependency)) {
                    throw new RuntimeException('Invalid importer dependency.');
                }
                $visit($dependency);
            }
            $tables = $item['source_tables'] ?? [];
            foreach ($tables as $table) {
                if (! is_string($table) || ! isset($available[$table])) {
                    throw new RuntimeException('Required legacy source table is missing.');
                }
            }
            unset($visiting[$id]);
            $visited[$id] = true;
            $ordered[] = ['id' => $id, 'source_tables' => array_values($tables),
                'depends_on' => array_values($item['depends_on'] ?? [])];
        };
        foreach (array_keys($byId) as $id) {
            $visit($id);
        }
        return ['mode' => 'read_only_plan', 'execution_allowed' => false, 'importers' => $ordered];
    }
}
