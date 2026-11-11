<?php

namespace Epesi\Modules\MigrationWizard\Import;

use PDO;
use RuntimeException;

/**
 * MariaDB-only checkpoint persistence. The caller must hold a transaction
 * on the same target PDO connection used to write imported records.
 */
final class MariaDbCheckpointStore
{
    public function initialize(PDO $target): void
    {
        if ($target->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('MariaDB checkpoint store requires a MySQL PDO connection.');
        }
        $target->exec('CREATE TABLE IF NOT EXISTS epesi_migration_checkpoints (
            importer_id VARCHAR(100) NOT NULL PRIMARY KEY,
            schema_fingerprint CHAR(64) NOT NULL,
            last_source_id BIGINT UNSIGNED NOT NULL,
            processed BIGINT UNSIGNED NOT NULL
        ) ENGINE=InnoDB');
    }

    public function read(PDO $target, string $importerId, bool $forUpdate = false): array
    {
        $query = $target->prepare('SELECT importer_id, schema_fingerprint, last_source_id, processed
            FROM epesi_migration_checkpoints WHERE importer_id = :id'.($forUpdate ? ' FOR UPDATE' : ''));
        $query->execute([':id' => $importerId]);
        return $query->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function save(PDO $target, array $next): void
    {
        if (! $target->inTransaction()) {
            throw new RuntimeException('Checkpoint must be saved in the target data transaction.');
        }
        $current = $this->read($target, $next['importer_id'], true);
        $validated = (new ImportCheckpoint)->advance($current, $next['importer_id'],
            $next['last_source_id'], $next['processed'], $next['schema_fingerprint']);
        $statement = $target->prepare('INSERT INTO epesi_migration_checkpoints
            (importer_id, schema_fingerprint, last_source_id, processed)
            VALUES (:id, :hash, :last, :processed)
            ON DUPLICATE KEY UPDATE schema_fingerprint = VALUES(schema_fingerprint),
                last_source_id = VALUES(last_source_id), processed = VALUES(processed)');
        $statement->execute([':id' => $validated['importer_id'],
            ':hash' => $validated['schema_fingerprint'], ':last' => $validated['last_source_id'],
            ':processed' => $validated['processed']]);
    }
}
