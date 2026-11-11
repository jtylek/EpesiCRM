<?php

use Epesi\Modules\MigrationWizard\Import\DisposableTargetGuard;
use Epesi\Modules\MigrationWizard\Import\Importers\UsersImporter;
use Epesi\Modules\MigrationWizard\Import\ImportRunLock;
use Epesi\Modules\MigrationWizard\Import\LegacyImporter;
use Epesi\Modules\MigrationWizard\Import\LegacyImporterRegistry;
use Epesi\Modules\MigrationWizard\Import\LegacyImportRunner;
use PHPUnit\Framework\TestCase;

final class MigrationWizardLegacyImportRunnerExecutionTest extends TestCase
{
    private const SCHEMA_HASH = '7355c7ea3ac4bf1343e9c59a8b750bb2786860506f3e7552fb9808fd07975bda';

    private const TOKEN = 'abc123abc123abc123abc123abc12300';

    private const SOURCE_COLUMNS = [
        'user_login' => ['id', 'login'],
        'company_data_1' => ['id', 'f_company_name'],
    ];

    private const TARGETS = [
        'users' => ['table' => 'users_t', 'id_column' => 'legacy_id', 'columns' => ['legacy_id', 'name']],
        'companies' => ['table' => 'companies_t', 'id_column' => 'legacy_id', 'columns' => ['legacy_id', 'company_name']],
    ];

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
        $dir = tempnam(sys_get_temp_dir(), 'epesi-runner-lock-');
        unlink($dir);
        $this->lockDirs[] = $dir;

        return new ImportRunLock($dir);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function source(): PDO
    {
        $source = $this->pdo();
        $source->exec('CREATE TABLE user_login (id INTEGER PRIMARY KEY, login TEXT)');
        $source->exec('CREATE TABLE company_data_1 (id INTEGER PRIMARY KEY, f_company_name TEXT)');
        $source->exec("INSERT INTO user_login VALUES (1, 'a'), (2, 'b')");
        $source->exec("INSERT INTO company_data_1 VALUES (1, 'C1'), (2, 'C2'), (3, 'C3')");

        return $source;
    }

    private function target(bool $disposable = true): PDO
    {
        $target = $this->pdo();
        $target->exec('CREATE TABLE users_t (legacy_id INTEGER UNIQUE, name TEXT NOT NULL)');
        $target->exec('CREATE TABLE companies_t (legacy_id INTEGER UNIQUE, company_name TEXT NOT NULL)');
        if ($disposable) {
            (new DisposableTargetGuard)->markDisposable($target, self::TOKEN);
        }

        return $target;
    }

    private function runner(): LegacyImportRunner
    {
        // Registered out of dependency order on purpose.
        $registry = new LegacyImporterRegistry;
        $registry->register($this->companiesImporter());
        $registry->register(new UsersImporter);

        return new LegacyImportRunner($registry);
    }

    private function execute(PDO $source, PDO $target, int $batchSize = 1): array
    {
        return $this->runner()->execute($source, $target, self::SOURCE_COLUMNS, self::TARGETS,
            self::SCHEMA_HASH, self::TOKEN, $this->lock(), $batchSize);
    }

    private function rows(PDO $target, string $table): int
    {
        return (int) $target->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    public function test_executes_jobs_in_dependency_order(): void
    {
        $source = $this->source();
        $target = $this->target();

        $result = $this->execute($source, $target, 2);

        self::assertSame(['users', 'companies'], array_column($result['jobs'], 'importer_id'));
        self::assertSame(2, $this->rows($target, 'users_t'));
        self::assertSame(3, $this->rows($target, 'companies_t'));
        self::assertSame('a', $target->query('SELECT name FROM users_t WHERE legacy_id = 1')->fetchColumn());
    }

    public function test_a_failing_job_stops_the_run_and_dependents_never_run(): void
    {
        $source = $this->source();
        $source->exec('UPDATE user_login SET login = NULL WHERE id = 2');
        $target = $this->target();

        try {
            $this->execute($source, $target);
            self::fail('Expected the run to stop.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString("Import stopped at job 'users'", $e->getMessage());
            self::assertNotNull($e->getPrevious());
        }

        self::assertSame(1, $this->rows($target, 'users_t'));
        self::assertSame(0, $this->rows($target, 'companies_t'));
    }

    public function test_resumes_at_the_interrupted_job_and_batch_without_rewriting_earlier_work(): void
    {
        $source = $this->source();
        $source->exec('UPDATE company_data_1 SET f_company_name = NULL WHERE id = 3');
        $target = $this->target();

        try {
            $this->execute($source, $target);
            self::fail('Expected the run to stop.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString("job 'companies'", $e->getMessage());
        }
        self::assertSame(2, $this->rows($target, 'companies_t'));

        // Proves the completed users job is skipped, not rewritten, on resume.
        $target->exec("UPDATE users_t SET name = 'kept' WHERE legacy_id = 1");
        $source->exec("UPDATE company_data_1 SET f_company_name = 'C3' WHERE id = 3");

        $result = $this->execute($source, $target);

        self::assertSame(3, $result['jobs'][1]['processed']);
        self::assertSame(3, $this->rows($target, 'companies_t'));
        self::assertSame('kept', $target->query('SELECT name FROM users_t WHERE legacy_id = 1')->fetchColumn());
    }

    public function test_rerun_after_success_writes_nothing_twice(): void
    {
        $source = $this->source();
        $target = $this->target();
        $this->execute($source, $target);
        $target->exec("UPDATE companies_t SET company_name = 'kept' WHERE legacy_id = 1");

        $again = $this->execute($source, $target);

        self::assertSame([2, 3], array_column($again['jobs'], 'processed'));
        self::assertSame(2, $this->rows($target, 'users_t'));
        self::assertSame(3, $this->rows($target, 'companies_t'));
        self::assertSame('kept', $target->query('SELECT company_name FROM companies_t WHERE legacy_id = 1')->fetchColumn());
    }

    public function test_refuses_a_target_that_is_not_marked_disposable_before_any_write(): void
    {
        $source = $this->source();
        $target = $this->target(disposable: false);

        try {
            $this->execute($source, $target);
            self::fail('Expected refusal.');
        } catch (RuntimeException $e) {
            self::assertSame('Target disposable-target token is missing or does not match.', $e->getMessage());
        }

        self::assertSame(0, $this->rows($target, 'users_t'));
        self::assertSame(0, $this->rows($target, 'companies_t'));
    }

    public function test_an_invalid_later_job_is_rejected_before_the_first_write(): void
    {
        $source = $this->source();
        $target = $this->target();
        $targets = self::TARGETS;
        $targets['companies']['columns'] = ['legacy_id'];

        try {
            $this->runner()->execute($source, $target, self::SOURCE_COLUMNS, $targets,
                self::SCHEMA_HASH, self::TOKEN, $this->lock());
            self::fail('Expected validation failure.');
        } catch (RuntimeException) {
        }

        self::assertSame(0, $this->rows($target, 'users_t'));
    }

    public function test_dry_run_is_the_default_and_writes_nothing(): void
    {
        $source = $this->source();
        $changesBefore = (int) $source->query('SELECT total_changes()')->fetchColumn();

        $plan = $this->runner()->dryRun($source, self::SOURCE_COLUMNS,
            ['users' => ['legacy_id', 'name'], 'companies' => ['legacy_id', 'company_name']]);

        self::assertSame('read_only_dry_run', $plan['mode']);
        self::assertFalse($plan['execution_allowed']);
        self::assertSame($changesBefore, (int) $source->query('SELECT total_changes()')->fetchColumn());
    }

    private function companiesImporter(): LegacyImporter
    {
        return new class implements LegacyImporter
        {
            public function id(): string
            {
                return 'companies';
            }

            public function dependencies(): array
            {
                return ['users'];
            }

            public function sourceTables(): array
            {
                return ['company_data_1'];
            }

            public function sourceFileAreas(): array
            {
                return [];
            }

            public function sourceTable(): string
            {
                return 'company_data_1';
            }

            public function sourceIdColumn(): string
            {
                return 'id';
            }

            public function fieldMapping(): array
            {
                return ['id' => 'legacy_id', 'f_company_name' => 'company_name'];
            }

            public function destinationModels(): array
            {
                return [];
            }

            public function verificationRules(): array
            {
                return [];
            }

            public function deferredMappings(): array
            {
                return [];
            }
        };
    }
}
