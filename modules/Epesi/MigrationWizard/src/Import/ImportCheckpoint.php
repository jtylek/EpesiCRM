<?php

namespace Epesi\Modules\MigrationWizard\Import;

use RuntimeException;

/**
 * Pure checkpoint validation for a future resumable importer runner.
 * No writes: persistence and locking belong to MigrationState.
 */
final class ImportCheckpoint
{
    public function advance(array $checkpoint, string $importerId, int $lastSourceId, int $processed, string $schemaFingerprint): array
    {
        if (! preg_match('/^[a-z][a-z0-9_-]*$/D', $importerId)
            || ! preg_match('/^[a-f0-9]{64}$/D', $schemaFingerprint)
            || $lastSourceId < 0 || $processed < 0) {
            throw new RuntimeException('Invalid importer checkpoint.');
        }
        if ($checkpoint !== []) {
            if (($checkpoint['importer_id'] ?? null) !== $importerId
                || ($checkpoint['schema_fingerprint'] ?? null) !== $schemaFingerprint
                || $lastSourceId < ($checkpoint['last_source_id'] ?? -1)
                || $processed < ($checkpoint['processed'] ?? -1)) {
                throw new RuntimeException('Checkpoint regression or source schema change.');
            }
        }
        return [
            'importer_id' => $importerId,
            'schema_fingerprint' => $schemaFingerprint,
            'last_source_id' => $lastSourceId,
            'processed' => $processed,
        ];
    }
}
