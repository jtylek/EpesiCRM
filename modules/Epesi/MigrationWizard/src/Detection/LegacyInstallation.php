<?php

namespace Epesi\Modules\MigrationWizard\Detection;

use PDO;
use RuntimeException;

/**
 * Read-only MySQL/MariaDB inventory. Never executes legacy application code.
 * An unapproved release (version not 1.9.1 from both the database and the files)
 * is assessment-only and cannot authorize migration. Within an approved release
 * each legacy module is approved on its own tables (see ModuleCoverage); extra
 * Premium/Custom/unknown tables never make the installation unapproved.
 */
final class LegacyInstallation
{
    public function __construct(
        private readonly LegacyConfigReader $reader,
        private readonly SchemaFingerprint $fingerprint,
        private readonly ModuleCoverage $coverage,
        private readonly LegacyInventory $inventory = new LegacyInventory,
        /** @var list<array<string, mixed>> */
        private readonly array $knownModules = [],
        /** @var list<array<string, mixed>> */
        private readonly array $importers = [],
        /** @var list<array{version: string, modules: array<string, list<array<string, string>>>}> */
        private readonly array $approvedSchemas = [],
        private readonly LegacyModuleData $moduleData = new LegacyModuleData,
        /** @var array<string, list<string>> legacy module => the registered importer tabs that migrate it (InPlaceLegacyImport::moduleImporters) */
        private readonly array $inPlaceImporters = [],
    ) {}

    public function assess(string $configPath): array
    {
        $config = $this->reader->read($configPath);
        $driver = strtolower($config['DATABASE_DRIVER'] ?? 'mysql');
        if (! in_array($driver, ['mysql', 'mysqli'], true)) {
            throw new RuntimeException('Only MySQL/MariaDB legacy assessment is supported.');
        }
        $pdo = new PDO(
            'mysql:host='.$config['DATABASE_HOST'].';port='.$config['DATABASE_PORT'].';dbname='.$config['DATABASE_NAME'].';charset=utf8mb4',
            $config['DATABASE_USER'],
            $config['DATABASE_PASSWORD'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY'],
        );
        $inventory = $this->inventory->build($pdo, dirname($configPath, 2));
        $tables = array_map(static fn (array $table): array => $table['columns'], $inventory['tables']);
        $signature = $this->fingerprint->hasLegacySignature($tables);
        $statistics = [];
        foreach ($inventory['tables'] as $name => $table) {
            $statistics[] = [
                'table' => (string) $name,
                'estimated_rows' => $table['estimated_rows'],
                'estimated_bytes' => $table['estimated_bytes'],
                'engine' => $table['engine'],
            ];
        }
        // The single fail-closed classification path: nothing is a candidate for
        // import unless ModuleCoverage::report() says so for this exact schema.
        $coverage = $this->coverage->report($inventory, $this->knownModules, $this->importers, $this->approvedSchemas, $this->inPlaceImporters);
        $compatible = $signature && $coverage['schema_approved'];
        // Which installed modules own recordsets / plain tables with data (read-only; never fails the assessment).
        try {
            $moduleData = $this->moduleData->build($pdo, dirname($configPath, 2), $inventory);
        } catch (\Throwable) {
            $moduleData = null;
        }

        return [
            'database' => $config['DATABASE_NAME'],
            'version' => $inventory['version'],
            'version_sources' => $inventory['version_sources'],
            'table_count' => count($tables),
            'table_statistics' => $statistics,
            'row_counts_are_estimates' => true,
            'tables' => array_keys($tables),
            'columns' => $tables,
            'coverage' => $coverage,
            'module_data' => $moduleData,
            'file_inventory_incomplete' => $inventory['file_scan_incomplete'],
            'fingerprint' => $inventory['fingerprint'],
            'legacy_signature' => $signature,
            'compatible' => $compatible,
            'blocked_modules' => $coverage['blocked_modules'],
            'warning' => ! $signature
                ? 'Required legacy tables are missing.'
                : ($compatible
                    ? ($coverage['blocked_modules'] === []
                        ? 'Approved legacy release; every known module matches its approved shape. Importer coverage is reported per table.'
                        : 'Approved legacy release, but these modules differ from the approved shape and are not migrated (their data is preserved): '.implode(', ', array_column($coverage['blocked_modules'], 'legacy_module')).'.')
                    : 'Legacy release is not approved (the version must be 1.9.1 in both the database and the files); assessment only.'),
        ];
    }

    /**
     * The same coverage classification for the tables the wizard has already
     * namespaced: the legacy tables are found through the exact rename map
     * and reported under their original names. Read-only.
     *
     * @param  array<string, string>  $renameMap  original => current
     * @return array<string, mixed> ModuleCoverage::report() output
     */
    public function coverageFor(PDO $pdo, string $legacyRoot, array $renameMap): array
    {
        return $this->coverage->report(
            $this->inventory->build($pdo, $legacyRoot, LegacyInventory::MAX_FILES, $renameMap),
            $this->knownModules,
            $this->importers,
            $this->approvedSchemas,
            $this->inPlaceImporters,
        );
    }
}
