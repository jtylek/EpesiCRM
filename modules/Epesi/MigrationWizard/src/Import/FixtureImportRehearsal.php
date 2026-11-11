<?php

namespace Epesi\Modules\MigrationWizard\Import;

use PDO;
use RuntimeException;

/**
 * Isolated target-table rehearsal using a synthetic fixture, not production migration.
 * Repeated batches are idempotent through a certified legacy-ID unique key.
 */
final class FixtureImportRehearsal
{
    public function run(PDO $source, PDO $target, array $mapping, int $batchSize = 100): array
    {
        if ($batchSize < 1 || $batchSize > 1000 || ($mapping['source_table'] ?? '') !== 'fixture_contacts'
            || ($mapping['target_table'] ?? '') !== 'fixture_contacts_imported'
            || ($mapping['id_column'] ?? '') !== 'id') {
            throw new RuntimeException('Only isolated synthetic fixture migration is enabled.');
        }
        $reader = new LegacyBatchReader;
        $store = new FixtureCheckpointStore;
        $store->initialize($target);
        $hash = str_repeat('f', 64);
        $cursor = $store->read($target, 'fixture_contacts');
        if ($cursor !== [] && $cursor['schema_fingerprint'] !== $hash) {
            throw new RuntimeException('Fixture checkpoint schema fingerprint mismatch.');
        }
        $count = (int) ($cursor['processed'] ?? 0);
        do {
            $batch = $reader->read($source, 'fixture_contacts', 'id',
                $cursor['last_source_id'] ?? 0, $batchSize, ['id', 'name']);
            $target->beginTransaction();
            try {
                $insert = $target->prepare('INSERT INTO fixture_contacts_imported (legacy_id, display_name) VALUES (:id, :name) ON CONFLICT(legacy_id) DO UPDATE SET display_name = excluded.display_name');
                foreach ($batch['rows'] as $row) {
                    $insert->execute([':id' => (int) $row['id'], ':name' => $row['name']]);
                }
                $next = (new ImportCheckpoint)->advance($cursor, 'fixture_contacts',
                    $batch['last_source_id'], $count + count($batch['rows']), $hash);
                $store->save($target, $next);
                $target->commit();
                $cursor = $next;
                $count += count($batch['rows']);
            } catch (\Throwable $e) {
                $target->rollBack();
                throw $e;
            }
        } while ($batch['has_more']);
        return ['mode' => 'synthetic_fixture_only', 'processed' => $count, 'checkpoint' => $cursor];
    }
}
