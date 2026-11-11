<?php

use App\Services\LegacyImport\ImporterRegistry;
use App\Services\LegacyImport\LegacyImportSet;
use Epesi\Modules\MigrationWizard\Import\InPlaceLegacyImport;
use Epesi\Modules\MigrationWizard\Orchestration\LivePhases;
use PHPUnit\Framework\TestCase;

final class MigrationWizardLivePhasesTest extends TestCase
{
    private string $base;

    private string $root;

    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/epesi-live-'.bin2hex(random_bytes(6));
        $this->root = $this->base.'/site';
        mkdir($this->root.'/data', 0700, true);
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE user_login (id INTEGER)');
    }

    protected function tearDown(): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            ($entry->isDir() && ! $entry->isLink()) ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->base);
        parent::tearDown();
    }

    /** @return array<string, mixed> the state once the administrator typed the backup confirmation */
    private function backup(): array
    {
        return ['preupgrade_backup' => ['confirmed_at' => '2026-01-01T00:00:00+00:00']];
    }

    /** @param  array<string, mixed>  $report */
    /** @return array<string, mixed> an approved assessment: the modules the always-run importers read match their shape */
    private static function approved(array $override = []): array
    {
        $module = static fn (string $name, bool $approved = true, array $differences = []): array => ['legacy_module' => $name, 'schema_approved' => $approved, 'schema_differences' => $differences];

        return [...['compatible' => true, 'database' => 'epesi', 'tables' => ['user_login'], 'coverage' => ['modules' => [$module('Base_User'), $module('CRM_Contacts')]]], ...$override];
    }

    private function phases(?array $report = null, string $database = 'epesi', bool $cleanup = false): LivePhases
    {
        $report ??= self::approved();

        return new LivePhases(
            $this->root,
            $this->pdo,
            import: new InPlaceLegacyImport(new LegacyImportSet(new ImporterRegistry)),
            assessor: static fn (string $path): array => $report,
            cleanupEnabled: $cleanup,
            databaseName: $database,
        );
    }

    private function assertNothingChanged(): void
    {
        self::assertSame(['user_login'], $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN));
    }

    public function test_a_freeze_is_refused_without_the_confirmed_backup_and_needs_no_maintenance_mode(): void
    {
        // An old recorded backup in the state does not count; only the typed confirmation does.
        foreach ([[], ['preupgrade_backup' => null], ['backup' => ['manifest' => '/nope.json', 'manifest_sha256' => str_repeat('a', 64)]]] as $state) {
            try {
                $this->phases()->freeze($state);
                self::fail('A freeze without the confirmed backup must be refused.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('backup', $e->getMessage());
            }
        }
        $this->assertNothingChanged();

        // No data/maintenance_mode.php exists here, and the freeze goes through.
        $this->assertFileDoesNotExist($this->root.'/data/maintenance_mode.php');
        $this->phases()->freeze($this->backup());
        $this->assertNothingChanged();
    }

    public function test_a_freeze_is_refused_for_a_schema_that_is_not_approved(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('approved');
        $this->phases(['compatible' => false, 'database' => 'epesi'])->freeze($this->backup());
    }

    public function test_a_freeze_is_refused_when_the_config_names_another_database(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not the database');
        $this->phases(self::approved(['database' => 'another_database']))->freeze($this->backup());
    }

    public function test_a_freeze_is_refused_when_a_core_module_the_import_needs_differs_from_its_approved_shape(): void
    {
        $differs = static fn (string $name): array => self::approved(['coverage' => ['modules' => [
            ['legacy_module' => 'Base_User', 'schema_approved' => $name !== 'Base_User', 'schema_differences' => [['table' => 'user_login', 'problem' => 'columns_differ']]],
            ['legacy_module' => 'CRM_Contacts', 'schema_approved' => $name !== 'CRM_Contacts', 'schema_differences' => [['table' => 'contact_data_1', 'problem' => 'columns_differ'], ['table' => 'company_data_1', 'problem' => 'missing']]],
        ]]]);

        foreach (['Base_User' => 'user_login', 'CRM_Contacts' => 'contact_data_1, company_data_1 (missing)'] as $module => $tables) {
            try {
                $this->phases($differs($module))->freeze($this->backup());
                self::fail('A core module that differs from the approved shape must refuse the freeze.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString("{$module} does not match its approved 1.9.1 shape ({$tables})", $e->getMessage());
            }
        }
        $this->assertNothingChanged();
    }

    public function test_a_freeze_is_refused_when_a_required_module_is_not_registered_or_there_is_no_coverage(): void
    {
        foreach ([self::approved(['coverage' => ['modules' => []]]), self::approved(['coverage' => []]), ['compatible' => true, 'database' => 'epesi']] as $report) {
            try {
                $this->phases($report)->freeze($this->backup());
                self::fail('No approved module registry entry must refuse the freeze.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('not in the installation', $e->getMessage());
            }
        }
    }

    public function test_premium_modules_and_extra_tables_in_the_report_do_not_stop_a_freeze(): void
    {
        $report = self::approved();
        $report['coverage']['modules'][] = ['legacy_module' => 'Premium_Warehouse', 'schema_approved' => null, 'schema_differences' => []];
        $report['tables'][] = 'premium_warehouse_items';

        $this->phases($report)->freeze($this->backup());

        $this->addToAssertionCount(1);
    }

    public function test_the_database_check_is_exact_and_fails_closed(): void
    {
        LivePhases::assertSameDatabase('epesi', 'epesi');
        foreach ([['epesi', 'epesi2'], ['', ''], ['epesi', null], ['epesi', ''], ['Epesi', 'epesi']] as [$configured, $connected]) {
            try {
                LivePhases::assertSameDatabase($configured, $connected);
                self::fail('Not the same database.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_freeze_passes_only_with_everything_in_place_and_changes_nothing(): void
    {
        $this->phases()->freeze($this->backup());

        $this->assertNothingChanged();
        $this->addToAssertionCount(1);
    }

    public function test_the_tables_to_rename_come_from_an_approved_schema_only(): void
    {
        self::assertSame(['user_login'], $this->phases()->legacyTables());

        $this->expectException(RuntimeException::class);
        $this->phases(['compatible' => false, 'tables' => ['user_login']])->legacyTables();
    }

    public function test_cleanup_is_off_unless_switched_on_and_even_then_needs_the_backup_confirmation_and_shipped_manifests(): void
    {
        $verification = ['status' => 'pass', 'cleanup_allowed' => true, 'cleanup_eligible_tables' => []];
        $map = ['user_login' => 'legacy_user_login'];

        // Switched off: nothing else is even looked at.
        try {
            $this->phases()->cleanup($verification, $map, $this->backup());
            self::fail('Cleanup is switched off.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('switched off', $e->getMessage());
        }

        // On, but the backup is not confirmed.
        try {
            $this->phases(cleanup: true)->cleanup($verification, $map, []);
            self::fail('Cleanup needs the confirmed backup.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('backup', $e->getMessage());
        }

        // On, backup confirmed, but the release's file list is there and broken: refused (a missing list is fine,
        // the top-level rules decide; a broken one is never trusted).
        if (! is_dir($this->root.'/update')) {
            mkdir($this->root.'/update', 0700, true);
        }
        file_put_contents($this->root.'/update/MANIFEST.txt', "not a manifest line\n");
        try {
            $this->phases(cleanup: true)->cleanup($verification, $map, $this->backup());
            self::fail('Cleanup trusted a malformed release list.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('malformed', $e->getMessage());
        }
        unlink($this->root.'/update/MANIFEST.txt');
        rmdir($this->root.'/update');
        $this->assertNothingChanged();
    }
}
