<?php

namespace Epesi\Modules\MigrationWizard\Import;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Disposable-target-only import execution harness.
 *
 * Builds on the existing LegacyBatchReader / *CheckpointStore / ImportCheckpoint
 * building blocks (reused, not reinvented) and adds:
 *  - a hard disposable-target gate (DisposableTargetGuard): never a live
 *    instance, never `epesisf`;
 *  - a run lock (ImportRunLock) so two runs cannot interleave writes;
 *  - a deterministic upsert keyed on the legacy id, so a resumed or rerun
 *    import never duplicates rows, regardless of target auto-increment state.
 *
 * Each batch's data write and checkpoint save happen in the same target
 * transaction (matching the existing FixtureImportRehearsal pattern), so a
 * forced interruption (process kill) leaves either the previous fully
 * committed state or nothing from the in-flight batch: resuming re-reads
 * the batch and writes are idempotent upserts, so no row is duplicated.
 *
 * Does not implement importer discovery, dependency ordering or an importer
 * registry: that is Milestone 11's LegacyImportRunner. Callers assemble one
 * ImportJob per importer and are responsible for running jobs in the order
 * their own dependencies require.
 */
final class ImportExecutor
{
    public function __construct(
        private readonly ImportRunLock $lock,
        private readonly LegacyBatchReader $reader = new LegacyBatchReader,
        private readonly DisposableTargetGuard $guard = new DisposableTargetGuard,
    ) {}

    /**
     * @return array{importer_id: string, processed: int, checkpoint: array, has_more: bool}
     */
    public function run(PDO $source, PDO $target, ImportJob $job, int $batchSize = 1000, ?int $maxBatches = null): array
    {
        if ($batchSize < 1 || $batchSize > 1000) {
            throw new RuntimeException('Invalid import batch size.');
        }
        if ($maxBatches !== null && $maxBatches < 1) {
            throw new RuntimeException('Invalid import batch limit.');
        }
        $this->guard->assertDisposable($target, $job->disposableToken);

        return $this->lock->withLock($job->lockKey(), function () use ($source, $target, $job, $batchSize, $maxBatches): array {
            $driver = $target->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver !== 'mysql' && $driver !== 'sqlite') {
                throw new RuntimeException('Unsupported import target driver.');
            }
            $store = $driver === 'mysql' ? new MariaDbCheckpointStore : new FixtureCheckpointStore;
            $store->initialize($target);
            $cursor = $store->read($target, $job->importerId);
            if ($cursor !== [] && $cursor['schema_fingerprint'] !== $job->schemaFingerprint) {
                throw new RuntimeException('Import checkpoint schema fingerprint mismatch.');
            }
            $afterId = (int) ($cursor['last_source_id'] ?? 0);
            $processed = (int) ($cursor['processed'] ?? 0);
            $insert = $this->prepareUpsert($target, $job, $driver);
            $batches = 0;
            $hasMore = true;
            while ($hasMore) {
                $batch = $this->reader->read($source, $job->sourceTable, $job->sourceIdColumn,
                    $afterId, $batchSize, $job->certifiedSourceColumns);
                $target->beginTransaction();
                try {
                    foreach ($batch['rows'] as $row) {
                        $bindings = [':target_id' => (int) $row[$job->sourceIdColumn]];
                        foreach ($job->columnMapping as $from => $to) {
                            $bindings[':col_'.$to] = $row[$from] ?? null;
                        }
                        $insert->execute($bindings);
                    }
                    $processed += count($batch['rows']);
                    $next = (new ImportCheckpoint)->advance($cursor, $job->importerId,
                        $batch['last_source_id'], $processed, $job->schemaFingerprint);
                    $store->save($target, $next);
                    $target->commit();
                } catch (Throwable $e) {
                    $target->rollBack();
                    throw $e;
                }
                $cursor = $next;
                $afterId = $batch['last_source_id'];
                $hasMore = $batch['has_more'];
                $batches++;
                if ($maxBatches !== null && $batches >= $maxBatches) {
                    break;
                }
            }

            return [
                'importer_id' => $job->importerId,
                'processed' => $processed,
                'checkpoint' => $cursor,
                'has_more' => $hasMore,
            ];
        });
    }

    private function prepareUpsert(PDO $target, ImportJob $job, string $driver): PDOStatement
    {
        $targets = array_values($job->columnMapping);
        $columns = array_merge([$job->targetIdColumn], $targets);
        $placeholders = array_merge([':target_id'], array_map(static fn (string $c): string => ':col_'.$c, $targets));
        $columnList = implode(', ', array_map(static fn (string $c): string => '`'.$c.'`', $columns));
        $placeholderList = implode(', ', $placeholders);
        $updates = implode(', ', array_map(
            static fn (string $c): string => $driver === 'mysql'
                ? '`'.$c.'` = VALUES(`'.$c.'`)'
                : '`'.$c.'` = excluded.`'.$c.'`',
            $targets
        ));
        $sql = $driver === 'mysql'
            ? "INSERT INTO `{$job->targetTable}` ({$columnList}) VALUES ({$placeholderList}) ON DUPLICATE KEY UPDATE {$updates}"
            : "INSERT INTO `{$job->targetTable}` ({$columnList}) VALUES ({$placeholderList}) ON CONFLICT(`{$job->targetIdColumn}`) DO UPDATE SET {$updates}";

        return $target->prepare($sql);
    }
}
