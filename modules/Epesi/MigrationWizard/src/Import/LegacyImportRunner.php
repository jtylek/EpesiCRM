<?php

namespace Epesi\Modules\MigrationWizard\Import;

use PDO;
use RuntimeException;
use Throwable;

final class LegacyImportRunner
{
    public function __construct(private LegacyImporterRegistry $registry) {}

    /**
     * @param  array<string, list<string>>  $sourceColumnsByTable
     * @param  array<string, list<string>>  $targetColumnsByImporter
     * @return array{
     *     mode: string,
     *     execution_allowed: false,
     *     importers: list<array<string, mixed>>
     * }
     */
    public function dryRun(
        PDO $source,
        array $sourceColumnsByTable,
        array $targetColumnsByImporter,
        int $batchSize = 100,
    ): array {
        $importers = [];
        $definitions = [];
        foreach ($this->registry->all() as $importer) {
            $id = $importer->id();
            $importers[$id] = $importer;
            $definitions[] = [
                'id' => $id,
                'depends_on' => $importer->dependencies(),
                'source_tables' => $importer->sourceTables(),
            ];
        }

        $plan = (new ImportPlan)->build($definitions, array_keys($sourceColumnsByTable));
        $reader = new LegacyBatchReader;
        $fieldMapping = new FieldMapping;
        $plannedImporters = [];

        foreach ($plan['importers'] as $planned) {
            $importer = $importers[$planned['id']];
            $table = LegacyImporterRegistry::assertPrimaryTable($importer);
            $mapping = $fieldMapping->validate(
                $importer->fieldMapping(),
                $sourceColumnsByTable[$table],
                $targetColumnsByImporter[$importer->id()] ?? [],
            );
            $batch = $reader->read(
                $source,
                $table,
                $importer->sourceIdColumn(),
                0,
                $batchSize,
                array_keys($mapping['mapping']),
            );

            $plannedImporters[] = [
                ...$planned,
                'mapping' => $mapping,
                'source_file_areas' => $importer->sourceFileAreas(),
                'destination_models' => $importer->destinationModels(),
                'verification' => [
                    'rules' => $importer->verificationRules(),
                    'status' => 'not_run',
                ],
                'preview' => [
                    'sampled_rows' => count($batch['rows']),
                    'last_source_id' => $batch['last_source_id'],
                    'has_more' => $batch['has_more'],
                ],
                'deferred_mappings' => $importer->deferredMappings(),
            ];
        }

        return [
            'mode' => 'read_only_dry_run',
            'execution_allowed' => false,
            'importers' => $plannedImporters,
        ];
    }

    /**
     * Execute the dependency-ordered plan job by job through ImportExecutor.
     *
     * Opt-in only: dryRun stays the default and never writes. Every job is built
     * and validated, and the target checked as disposable, before the first
     * write. One run lock covers the whole run. The first failing job stops the
     * run, so no later job executes. Calling again resumes from each checkpoint.
     *
     * @param  array<string, list<string>>  $sourceColumnsByTable
     * @param  array<string, array{table: string, id_column: string, columns: list<string>}>  $targetsByImporter
     * @return array{mode: string, jobs: list<array<string, mixed>>}
     */
    public function execute(
        PDO $source,
        PDO $target,
        array $sourceColumnsByTable,
        array $targetsByImporter,
        string $schemaFingerprint,
        string $disposableToken,
        ImportRunLock $lock,
        int $batchSize = 1000,
    ): array {
        (new DisposableTargetGuard)->assertDisposable($target, $disposableToken);

        $importers = [];
        $definitions = [];
        foreach ($this->registry->all() as $importer) {
            $importers[$importer->id()] = $importer;
            $definitions[] = [
                'id' => $importer->id(),
                'depends_on' => $importer->dependencies(),
                'source_tables' => $importer->sourceTables(),
            ];
        }
        $plan = (new ImportPlan)->build($definitions, array_keys($sourceColumnsByTable));

        $jobs = [];
        foreach ($plan['importers'] as $planned) {
            $importer = $importers[$planned['id']];
            LegacyImporterRegistry::assertPrimaryTable($importer);
            $spec = $targetsByImporter[$importer->id()] ?? null;
            if ($spec === null) {
                throw new RuntimeException('No import target is configured for an importer.');
            }
            // ImportExecutor writes the target id from the source id, so that pair is not a mapped column.
            $mapping = array_filter($importer->fieldMapping(), static fn (string $to): bool => $to !== $spec['id_column']);
            $jobs[] = new ImportJob(
                importerId: $importer->id(),
                schemaFingerprint: $schemaFingerprint,
                sourceTable: $importer->sourceTable(),
                sourceIdColumn: $importer->sourceIdColumn(),
                certifiedSourceColumns: array_values(array_unique([$importer->sourceIdColumn(), ...array_keys($mapping)])),
                targetTable: $spec['table'],
                targetIdColumn: $spec['id_column'],
                certifiedTargetColumns: $spec['columns'],
                columnMapping: $mapping,
                disposableToken: $disposableToken,
            );
        }

        return $lock->withLock('migration-run', function () use ($jobs, $source, $target, $lock, $batchSize): array {
            $executor = new ImportExecutor($lock);
            $results = [];
            foreach ($jobs as $job) {
                try {
                    $results[] = $executor->run($source, $target, $job, $batchSize);
                } catch (Throwable $e) {
                    throw new RuntimeException(
                        "Import stopped at job '{$job->importerId}' after ".count($results).' completed job(s): '.$e->getMessage(),
                        0,
                        $e,
                    );
                }
            }

            return ['mode' => 'executed_disposable_target', 'jobs' => $results];
        });
    }
}
