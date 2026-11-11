<?php

use Epesi\Modules\MigrationWizard\Import\DisposableTargetGuard;
use Epesi\Modules\MigrationWizard\Import\ImportExecutor;
use Epesi\Modules\MigrationWizard\Import\ImportJob;
use Epesi\Modules\MigrationWizard\Import\ImportRunLock;
use PHPUnit\Framework\TestCase;

final class MigrationWizardImportExecutorTest extends TestCase
{
    private const SCHEMA_HASH = '7355c7ea3ac4bf1343e9c59a8b750bb2786860506f3e7552fb9808fd07975bda';

    private const TOKEN = 'abc123abc123abc123abc123abc12300';

    private array $lockDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->lockDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        $this->lockDirs = [];
        parent::tearDown();
    }

    private function lock(): ImportRunLock
    {
        $dir = tempnam(sys_get_temp_dir(), 'epesi-import-lock-');
        if ($dir === false) {
            self::fail('Unable to allocate a disposable lock directory.');
        }
        unlink($dir);
        $this->lockDirs[] = $dir;

        return new ImportRunLock($dir);
    }

    private function source(array $rows): PDO
    {
        $source = new PDO('sqlite::memory:');
        $source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $source->exec('CREATE TABLE legacy_contacts (id INTEGER PRIMARY KEY, name TEXT)');
        $insert = $source->prepare('INSERT INTO legacy_contacts (id, name) VALUES (:id, :name)');
        foreach ($rows as [$id, $name]) {
            $insert->execute([':id' => $id, ':name' => $name]);
        }

        return $source;
    }

    private function disposableTarget(): PDO
    {
        $target = new PDO('sqlite::memory:');
        $target->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $target->exec('CREATE TABLE contacts_imported (legacy_id INTEGER UNIQUE, display_name TEXT)');
        (new DisposableTargetGuard)->markDisposable($target, self::TOKEN);

        return $target;
    }

    private function job(): ImportJob
    {
        return new ImportJob(
            importerId: 'contacts',
            schemaFingerprint: self::SCHEMA_HASH,
            sourceTable: 'legacy_contacts',
            sourceIdColumn: 'id',
            certifiedSourceColumns: ['id', 'name'],
            targetTable: 'contacts_imported',
            targetIdColumn: 'legacy_id',
            certifiedTargetColumns: ['legacy_id', 'display_name'],
            columnMapping: ['name' => 'display_name'],
            disposableToken: self::TOKEN,
        );
    }

    public function test_normal_run_imports_all_rows_with_deterministic_target_ids(): void
    {
        $source = $this->source([[1, 'A'], [3, 'B'], [8, 'C'], [10, 'D'], [15, 'E']]);
        $target = $this->disposableTarget();

        $result = (new ImportExecutor($this->lock()))->run($source, $target, $this->job(), batchSize: 2);

        self::assertSame(5, $result['processed']);
        self::assertSame(15, $result['checkpoint']['last_source_id']);
        self::assertFalse($result['has_more']);
        self::assertSame(5, (int) $target->query('SELECT COUNT(*) FROM contacts_imported')->fetchColumn());
        $ids = $target->query('SELECT legacy_id FROM contacts_imported ORDER BY legacy_id')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([1, 3, 8, 10, 15], array_map('intval', $ids));
    }

    public function test_interrupted_run_resumes_without_duplicating_rows(): void
    {
        $source = $this->source([[1, 'A'], [3, 'B'], [8, 'C'], [10, 'D'], [15, 'E']]);
        $target = $this->disposableTarget();
        $executor = new ImportExecutor($this->lock());
        $job = $this->job();

        // Simulate a forced interruption: only the first batch is allowed to run and commit.
        $partial = $executor->run($source, $target, $job, batchSize: 2, maxBatches: 1);
        self::assertSame(2, $partial['processed']);
        self::assertTrue($partial['has_more']);
        self::assertSame(2, (int) $target->query('SELECT COUNT(*) FROM contacts_imported')->fetchColumn());

        // Resume: a fresh executor/lock instance picks up from the durable checkpoint.
        $resumed = (new ImportExecutor($this->lock()))->run($source, $target, $job, batchSize: 2);
        self::assertSame(5, $resumed['processed']);
        self::assertFalse($resumed['has_more']);
        self::assertSame(5, (int) $target->query('SELECT COUNT(*) FROM contacts_imported')->fetchColumn());
        $ids = $target->query('SELECT legacy_id FROM contacts_imported ORDER BY legacy_id')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([1, 3, 8, 10, 15], array_map('intval', $ids));
    }

    public function test_rerun_after_success_does_not_duplicate_rows(): void
    {
        $source = $this->source([[1, 'A'], [3, 'B'], [8, 'C']]);
        $target = $this->disposableTarget();
        $job = $this->job();

        $first = (new ImportExecutor($this->lock()))->run($source, $target, $job, batchSize: 10);
        self::assertSame(3, $first['processed']);

        $second = (new ImportExecutor($this->lock()))->run($source, $target, $job, batchSize: 10);
        self::assertSame(3, $second['processed']);
        self::assertSame($first['checkpoint'], $second['checkpoint']);
        self::assertSame(3, (int) $target->query('SELECT COUNT(*) FROM contacts_imported')->fetchColumn());
    }

    public function test_mid_batch_failure_rolls_back_and_a_later_retry_still_succeeds(): void
    {
        $source = $this->source([[1, 'A'], [3, 'B'], [8, 'C'], [10, 'D']]);
        $target = $this->disposableTarget();
        // Seed a row that collides with the second batch's mapped value, forcing the
        // insert for that batch to throw mid-transaction (simulating a fault mid-batch).
        $target->exec('CREATE UNIQUE INDEX contacts_display_name_unique ON contacts_imported (display_name)');
        $target->exec("INSERT INTO contacts_imported (legacy_id, display_name) VALUES (999, 'C')");
        $job = $this->job();
        $executor = new ImportExecutor($this->lock());

        try {
            $executor->run($source, $target, $job, batchSize: 2);
            self::fail('Expected the colliding second batch to throw.');
        } catch (Throwable) {
            // expected: second batch (ids 8, 10) collides on display_name 'C'
        }

        self::assertFalse($target->inTransaction());
        // Only the first, successfully committed batch (ids 1, 3) is present; the
        // failed batch left no partial rows and did not advance the checkpoint.
        self::assertSame(3, (int) $target->query('SELECT COUNT(*) FROM contacts_imported')->fetchColumn());
        $checkpoint = $target->query("SELECT last_source_id, processed FROM fixture_migration_checkpoint WHERE importer_id = 'contacts'")
            ->fetch(PDO::FETCH_ASSOC);
        self::assertSame(3, (int) $checkpoint['last_source_id']);
        self::assertSame(2, (int) $checkpoint['processed']);

        // The run lock was released on failure (via finally), so a retry is not blocked.
        $target->exec('DELETE FROM contacts_imported WHERE legacy_id = 999');
        $retry = (new ImportExecutor($this->lock()))->run($source, $target, $job, batchSize: 2);
        self::assertSame(4, $retry['processed']);
        self::assertSame(4, (int) $target->query('SELECT COUNT(*) FROM contacts_imported')->fetchColumn());
    }

    public function test_concurrent_run_is_rejected_while_the_lock_is_held(): void
    {
        $source = $this->source([[1, 'A']]);
        $target = $this->disposableTarget();
        $job = $this->job();
        $lockDir = tempnam(sys_get_temp_dir(), 'epesi-import-lock-');
        unlink($lockDir);
        $this->lockDirs[] = $lockDir;
        $lock = new ImportRunLock($lockDir);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Another import run holds this lock.');

        $lock->withLock('contacts', function () use ($source, $target, $job, $lockDir): void {
            // A second executor using an independent lock instance over the same
            // directory must fail to acquire the same key while this run holds it.
            (new ImportExecutor(new ImportRunLock($lockDir)))->run($source, $target, $job);
        });
    }

    public function test_refuses_to_run_against_a_target_not_marked_disposable(): void
    {
        $source = $this->source([[1, 'A']]);
        $target = new PDO('sqlite::memory:');
        $target->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $target->exec('CREATE TABLE contacts_imported (legacy_id INTEGER UNIQUE, display_name TEXT)');
        // Deliberately never marked disposable.

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target disposable-target token is missing or does not match.');

        (new ImportExecutor($this->lock()))->run($source, $target, $this->job());
    }
}
