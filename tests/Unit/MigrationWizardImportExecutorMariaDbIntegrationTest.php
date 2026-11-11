<?php

use Epesi\Modules\MigrationWizard\Import\DisposableTargetGuard;
use Epesi\Modules\MigrationWizard\Import\ImportExecutor;
use Epesi\Modules\MigrationWizard\Import\ImportJob;
use Epesi\Modules\MigrationWizard\Import\ImportRunLock;
use PHPUnit\Framework\TestCase;

/**
 * Opt-in real MariaDB integration test for the import execution harness.
 * Requires MIGRATION_TEST_MARIADB_DSN, _USER, _PASSWORD and a disposable
 * schema, exactly like MigrationWizardMariaDbCheckpointIntegrationTest. The
 * configured schema must never be a live installation's database.
 */
final class MigrationWizardImportExecutorMariaDbIntegrationTest extends TestCase
{
    public function test_executor_runs_and_resumes_against_real_mariadb(): void
    {
        $dsn = getenv('MIGRATION_TEST_MARIADB_DSN');
        if (! $dsn) {
            self::markTestSkipped('Disposable MariaDB DSN is not configured.');
        }
        $user = getenv('MIGRATION_TEST_MARIADB_USER') ?: '';
        $password = getenv('MIGRATION_TEST_MARIADB_PASSWORD') ?: '';
        $connect = static function () use ($dsn, $user, $password): PDO {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
                throw new RuntimeException('Expected MySQL/MariaDB.');
            }
            return $pdo;
        };

        $suffix = bin2hex(random_bytes(4));
        $sourceTable = 'epesi_migration_fixture_source_'.$suffix;
        $targetTable = 'epesi_migration_fixture_target_'.$suffix;
        $importerId = 'fixture_'.$suffix;
        // Stable across runs: a disposable target is marked once for the life of
        // that schema, not re-tokenized on every CI run (markDisposable rejects
        // re-marking an already-marked target with a different token).
        $token = hash('sha256', 'epesi-migration-wizard-ci-disposable-target');
        $token = substr($token, 0, 32);
        $fingerprint = hash('sha256', 'schema-'.$suffix);

        $target = $connect();
        $guard = new DisposableTargetGuard;
        self::assertFalse($guard->isForbiddenDatabaseName((string) $target->query('SELECT DATABASE()')->fetchColumn()),
            'The configured MIGRATION_TEST_MARIADB_DSN must point at a disposable, non-live database.');

        $source = $connect();
        $source->exec("CREATE TABLE `{$sourceTable}` (id BIGINT UNSIGNED PRIMARY KEY, name VARCHAR(100) NOT NULL) ENGINE=InnoDB");
        $target->exec("CREATE TABLE `{$targetTable}` (legacy_id BIGINT UNSIGNED PRIMARY KEY, display_name VARCHAR(100) NOT NULL) ENGINE=InnoDB");
        $guard->markDisposable($target, $token);

        $job = new ImportJob(
            importerId: $importerId,
            schemaFingerprint: $fingerprint,
            sourceTable: $sourceTable,
            sourceIdColumn: 'id',
            certifiedSourceColumns: ['id', 'name'],
            targetTable: $targetTable,
            targetIdColumn: 'legacy_id',
            certifiedTargetColumns: ['legacy_id', 'display_name'],
            columnMapping: ['name' => 'display_name'],
            disposableToken: $token,
        );
        $lockDir = sys_get_temp_dir().'/epesi-import-lock-'.$suffix;

        try {
            $insert = $source->prepare("INSERT INTO `{$sourceTable}` (id, name) VALUES (:id, :name)");
            foreach ([[1, 'A'], [3, 'B'], [8, 'C'], [10, 'D']] as [$id, $name]) {
                $insert->execute([':id' => $id, ':name' => $name]);
            }

            $partial = (new ImportExecutor(new ImportRunLock($lockDir)))
                ->run($source, $target, $job, batchSize: 2, maxBatches: 1);
            self::assertSame(2, $partial['processed']);
            self::assertTrue($partial['has_more']);

            // Resume after a simulated forced interruption with a fresh connection/executor.
            unset($target);
            $target = $connect();
            $guard->assertDisposable($target, $token);
            $resumed = (new ImportExecutor(new ImportRunLock($lockDir)))
                ->run($source, $target, $job, batchSize: 2);
            self::assertSame(4, $resumed['processed']);
            self::assertFalse($resumed['has_more']);

            $count = (int) $target->query("SELECT COUNT(*) FROM `{$targetTable}`")->fetchColumn();
            self::assertSame(4, $count);

            // Rerun after success must not duplicate rows.
            $again = (new ImportExecutor(new ImportRunLock($lockDir)))->run($source, $target, $job, batchSize: 2);
            self::assertSame(4, $again['processed']);
            self::assertSame(4, (int) $target->query("SELECT COUNT(*) FROM `{$targetTable}`")->fetchColumn());
        } finally {
            foreach (glob($lockDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($lockDir);
            $target->exec("DROP TABLE IF EXISTS `{$targetTable}`");
            $target->exec('DELETE FROM epesi_migration_checkpoints WHERE importer_id = '.$target->quote($importerId));
            $source->exec("DROP TABLE IF EXISTS `{$sourceTable}`");
        }
    }
}
