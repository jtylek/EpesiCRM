<?php

namespace Epesi\Modules\MigrationWizard\Import;

use RuntimeException;

/**
 * Certified, validated description of one importer's source-to-target
 * mapping for ImportExecutor. Building this performs no I/O; it only
 * rejects unsafe or incomplete configuration before any batch is read or
 * written. This is intentionally narrower than Milestone 11's eventual
 * importer registry/contract: it describes exactly one table-to-table
 * mapping and has no notion of dependency ordering between importers.
 */
final class ImportJob
{
    /** @var array<string, string> validated source-column => target-column map */
    public readonly array $columnMapping;

    /**
     * @param  string[]  $certifiedSourceColumns
     * @param  string[]  $certifiedTargetColumns
     * @param  array<string, string>  $columnMapping
     */
    public function __construct(
        public readonly string $importerId,
        public readonly string $schemaFingerprint,
        public readonly string $sourceTable,
        public readonly string $sourceIdColumn,
        public readonly array $certifiedSourceColumns,
        public readonly string $targetTable,
        public readonly string $targetIdColumn,
        public readonly array $certifiedTargetColumns,
        array $columnMapping,
        public readonly string $disposableToken,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_-]*$/D', $importerId)) {
            throw new RuntimeException('Invalid importer identifier.');
        }
        if (! preg_match('/^[a-f0-9]{64}$/D', $schemaFingerprint)) {
            throw new RuntimeException('Invalid schema fingerprint.');
        }
        foreach ([$sourceTable, $sourceIdColumn, $targetTable, $targetIdColumn] as $identifier) {
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $identifier)) {
                throw new RuntimeException('Unsafe import job identifier.');
            }
        }
        if (! in_array($sourceIdColumn, $certifiedSourceColumns, true)) {
            throw new RuntimeException('Source id column must be a certified source column.');
        }
        if (! in_array($targetIdColumn, $certifiedTargetColumns, true)) {
            throw new RuntimeException('Target id column must be a certified target column.');
        }
        $validated = (new FieldMapping)->validate($columnMapping, $certifiedSourceColumns, $certifiedTargetColumns);
        if ($validated['mapping'] === []) {
            throw new RuntimeException('Import job requires at least one mapped column.');
        }
        foreach ($validated['mapping'] as $to) {
            if ($to === $targetIdColumn) {
                throw new RuntimeException('Target id column is written deterministically from the source id and must not also appear in the column mapping.');
            }
        }
        if ($disposableToken === '' || ! preg_match('/^[a-f0-9]{32,64}$/D', $disposableToken)) {
            throw new RuntimeException('Invalid disposable-target token.');
        }
        $this->columnMapping = $validated['mapping'];
    }

    public function lockKey(): string
    {
        return $this->importerId;
    }
}
