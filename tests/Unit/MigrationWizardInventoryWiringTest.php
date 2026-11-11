<?php

use Epesi\Modules\MigrationWizard\Checks\MigrationDryRun;
use Epesi\Modules\MigrationWizard\Detection\LegacyInventory;
use Epesi\Modules\MigrationWizard\Detection\ModuleCoverage;
use Epesi\Modules\MigrationWizard\Import\Importers\UsersImporter;
use Epesi\Modules\MigrationWizard\Import\LegacyImporter;
use Epesi\Modules\MigrationWizard\Import\LegacyImporterRegistry;
use PHPUnit\Framework\TestCase;

abstract class MigrationWizardInventoryWiringStubImporter implements LegacyImporter
{
    public function id(): string
    {
        return 'empty';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function sourceTables(): array
    {
        return [];
    }

    public function sourceFileAreas(): array
    {
        return [];
    }

    public function sourceTable(): string
    {
        return 'unused';
    }

    public function sourceIdColumn(): string
    {
        return 'id';
    }

    public function fieldMapping(): array
    {
        return [];
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
}

final class MigrationWizardInventoryWiringTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/epesi-inventory-'.bin2hex(random_bytes(6));
        mkdir($this->root.'/data/CRM_Contacts_Photo/sub', 0700, true);
        mkdir($this->root.'/data/Empty', 0700, true);
        mkdir($this->root.'/include', 0700, true);
        file_put_contents($this->root.'/data/config.php', '<?php // no secrets');
        file_put_contents($this->root.'/data/CRM_Contacts_Photo/a.jpg', 'aaaa');
        file_put_contents($this->root.'/data/CRM_Contacts_Photo/sub/b.jpg', 'bb');
        file_put_contents($this->root.'/include/version.php', "<?php\ndefine(\"EPESI_VERSION\", '1.9.1');\ndefine(\"EPESI_REVISION\", 20220911);\n");
    }

    protected function tearDown(): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->root);
        parent::tearDown();
    }

    private function metadata(?string $database = '1.9.1', ?string $file = '1.9.1'): array
    {
        return (new LegacyInventory)->fromMetadata(
            ['variables' => ['name', 'value'], 'modules' => ['name', 'version'], 'user_login' => ['id', 'login'], 'custom_invoice' => ['id']],
            ['custom_invoice' => ['estimated_rows' => 3, 'estimated_bytes' => 100, 'engine' => 'InnoDB']],
            [['name' => 'Base', 'version' => 1]],
            $database,
            $file,
            ['data/Custom_Invoice' => ['count' => 1, 'bytes' => 5]],
        );
    }

    public function test_inventory_feeds_the_coverage_report_and_everything_is_unclassified_by_default(): void
    {
        $inventory = $this->metadata();
        self::assertSame('1.9.1', $inventory['version']);
        $report = (new ModuleCoverage)->report($inventory);
        self::assertTrue($report['assessment_only']);
        self::assertSame(['custom_invoice', 'modules', 'user_login', 'variables'], $report['unclassified_tables']);
        self::assertSame(['data/Custom_Invoice'], $report['unclassified_file_areas']);
        self::assertSame(3, array_column($report['tables'], null, 'table')['custom_invoice']['estimated_rows']);
        foreach ($report['tables'] as $table) {
            self::assertSame('preserve', $table['disposition']);
        }
    }

    public function test_conflicting_or_missing_versions_are_unknown_and_never_approved(): void
    {
        self::assertSame('unknown', $this->metadata('1.9.1', '1.8.0')['version']);
        self::assertTrue($this->metadata('1.9.1', '1.8.0')['version_mismatch']);
        self::assertSame('unknown', $this->metadata(null, null)['version']);
        // Both sources must exist and agree: one alone is not an approved release.
        self::assertSame('unknown', $this->metadata(null, '1.9.1')['version']);
        self::assertSame('unknown', $this->metadata('1.9.1', null)['version']);
        self::assertSame('1.9.1', $this->metadata('1.9.1', '1.9.1')['version']);
        foreach ([$this->metadata('1.9.1', '1.8.0'), $this->metadata(null, '1.9.1'), $this->metadata('1.9.1', null)] as $inventory) {
            $report = (new ModuleCoverage)->report($inventory, [], [], [['version' => '1.9.1', 'modules' => []]]);
            self::assertFalse($report['schema_approved']);
        }
    }

    public function test_serialized_version_is_parsed_not_unserialized(): void
    {
        self::assertSame('1.9.1', LegacyInventory::parseSerializedVersion('s:5:"1.9.1";'));
        self::assertSame('1.9.1', LegacyInventory::parseSerializedVersion('1.9.1'));
        self::assertNull(LegacyInventory::parseSerializedVersion('O:8:"stdClass":0:{}'));
        self::assertNull(LegacyInventory::parseSerializedVersion('s:9:"1.9.1<?php";'));
    }

    public function test_version_file_is_parsed_without_being_executed(): void
    {
        self::assertSame('1.9.1', LegacyInventory::readFileVersion($this->root));
        file_put_contents($this->root.'/include/version.php', '<?php throw new Exception("executed");');
        self::assertNull(LegacyInventory::readFileVersion($this->root));
        self::assertNull(LegacyInventory::readFileVersion($this->root.'/missing'));
    }

    public function test_file_areas_are_top_level_directories_plus_loose_files(): void
    {
        [$areas, $incomplete] = (new LegacyInventory)->scanDataAreas($this->root.'/data', 100);
        self::assertFalse($incomplete);
        self::assertSame(['count' => 2, 'bytes' => 6], $areas['data/CRM_Contacts_Photo']);
        self::assertSame(['count' => 0, 'bytes' => 0], $areas['data/Empty']);
        self::assertSame(1, $areas[LegacyInventory::LOOSE_DATA_FILES]['count']);
    }

    public function test_file_scan_cap_marks_the_inventory_incomplete(): void
    {
        [, $incomplete] = (new LegacyInventory)->scanDataAreas($this->root.'/data', 1);
        self::assertTrue($incomplete);
        [$areas, $missing] = (new LegacyInventory)->scanDataAreas($this->root.'/nope', 10);
        self::assertSame([], $areas);
        self::assertTrue($missing);
    }

    public function test_dry_run_only_marks_tables_the_coverage_report_supports(): void
    {
        $assessment = [
            'fingerprint' => 'x', 'legacy_signature' => true, 'compatible' => false,
            'table_statistics' => [
                ['table' => 'contact_data_1', 'estimated_rows' => 10],
                ['table' => 'custom_private', 'estimated_rows' => 7],
            ],
            'coverage' => ['schema_approved' => true, 'unclassified_tables' => ['custom_private'], 'tables' => [
                ['table' => 'contact_data_1', 'outcome' => 'supported', 'disposition' => 'candidate_for_import', 'unmapped_columns' => []],
                ['table' => 'custom_private', 'outcome' => 'unknown', 'disposition' => 'preserve', 'unmapped_columns' => ['a']],
            ]],
        ];
        $preview = (new MigrationDryRun)->preview($assessment);
        self::assertSame('read_only_preview', $preview['mode']);
        self::assertFalse($preview['execution_allowed']);
        self::assertSame(10, $preview['estimated_candidate_rows']);
        self::assertSame(7, $preview['estimated_preserved_rows']);
        self::assertSame('preserve_unmapped', $preview['tables'][1]['outcome']);
        self::assertSame(['custom_private'], $preview['unclassified_tables']);
    }

    public function test_dry_run_without_a_coverage_report_preserves_everything(): void
    {
        $preview = (new MigrationDryRun)->preview(['table_statistics' => [['table' => 'contact_data_1', 'estimated_rows' => 10]]]);
        self::assertSame(0, $preview['estimated_candidate_rows']);
        self::assertSame(10, $preview['estimated_preserved_rows']);
        self::assertFalse($preview['schema_approved']);
    }

    public function test_registry_rejects_an_importer_without_source_tables(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Legacy importer 'empty' declares no source tables");
        (new LegacyImporterRegistry)->register(new class extends MigrationWizardInventoryWiringStubImporter {});
    }

    public function test_registry_rejects_a_primary_table_that_is_not_the_first_source_table(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not the first of its declared source tables');
        (new LegacyImporterRegistry)->register(new class extends MigrationWizardInventoryWiringStubImporter
        {
            public function sourceTables(): array
            {
                return ['a', 'b'];
            }

            public function sourceTable(): string
            {
                return 'b';
            }
        });
    }

    public function test_a_valid_importer_still_registers(): void
    {
        $registry = new LegacyImporterRegistry;
        $registry->register(new UsersImporter);
        self::assertCount(1, $registry->all());
    }
}
