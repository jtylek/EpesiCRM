<?php

namespace Epesi\Modules\MigrationWizard\Import;

use PDO;
use RuntimeException;

/**
 * Atomic target-side checkpoint for synthetic SQLite rehearsal.
 * Data writes and checkpoint must use the SAME transaction/connection.
 */
final class FixtureCheckpointStore
{
    public function initialize(PDO $target): void
    {
        $target->exec('CREATE TABLE IF NOT EXISTS fixture_migration_checkpoint (
            importer_id TEXT PRIMARY KEY, schema_fingerprint TEXT NOT NULL,
            last_source_id INTEGER NOT NULL, processed INTEGER NOT NULL)');
    }

    public function read(PDO $target, string $importerId): array
    {
        $query = $target->prepare('SELECT importer_id, schema_fingerprint, last_source_id, processed
            FROM fixture_migration_checkpoint WHERE importer_id = :id');
        $query->execute([':id' => $importerId]);
        return $query->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function save(PDO $target, array $next): void
    {
        if (! $target->inTransaction()) {
            throw new RuntimeException('Checkpoint must be saved in the target data transaction.');
        }
        $current = $this->read($target, $next['importer_id']);
        $validated = (new ImportCheckpoint)->advance($current, $next['importer_id'],
            $next['last_source_id'], $next['processed'], $next['schema_fingerprint']);
        $query = $target->prepare('INSERT INTO fixture_migration_checkpoint
            (importer_id, schema_fingerprint, last_source_id, processed)
            VALUES (:id, :hash, :last, :processed)
            ON CONFLICT(importer_id) DO UPDATE SET
                schema_fingerprint = excluded.schema_fingerprint,
                last_source_id = excluded.last_source_id,
                processed = excluded.processed');
        $query->execute([
            ':id' => $validated['importer_id'], ':hash' => $validated['schema_fingerprint'],
            ':last' => $validated['last_source_id'], ':processed' => $validated['processed'],
        ]);
    }
}
