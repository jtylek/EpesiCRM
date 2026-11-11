<?php

use Epesi\Modules\MigrationWizard\Verification\LeftBehindReport;
use PHPUnit\Framework\TestCase;

final class MigrationWizardLeftBehindReportTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/epesi-left-'.bin2hex(random_bytes(6));
        mkdir($this->root.'/modules/Premium/Warehouse/Items', 0775, true);
        mkdir($this->root.'/modules/Custom/Invoice', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->root);
        parent::tearDown();
    }

    public function test_it_lists_per_module_the_code_folder_the_legacy_tables_and_the_data_folders_and_the_unreferenced_files(): void
    {
        $coverage = [
            'modules' => [
                ['legacy_module' => 'Premium_Warehouse', 'outcome' => 'unknown'],
                ['legacy_module' => 'Custom_Invoice', 'outcome' => 'unknown'],
                ['legacy_module' => 'CRM_Contacts', 'outcome' => 'supported'],
            ],
            'tables' => [
                ['table' => 'premium_warehouse_items', 'legacy_module' => 'Premium_Warehouse', 'estimated_rows' => 120],
                ['table' => 'premium_warehouse_orders', 'legacy_module' => 'Premium_Warehouse', 'estimated_rows' => 7],
                ['table' => 'contact_data_1', 'legacy_module' => 'CRM_Contacts', 'estimated_rows' => 500],
                ['table' => 'mystery', 'legacy_module' => null, 'estimated_rows' => 3],
            ],
            'files' => [
                ['area' => 'data/Premium_Warehouse', 'legacy_module' => 'Premium_Warehouse', 'count' => 4, 'bytes' => 900],
                ['area' => 'data/Utils_FileStorage', 'legacy_module' => 'Utils_FileStorage', 'count' => 90, 'bytes' => 5000],
            ],
        ];
        $verification = [
            'preserved_tables' => ['premium_warehouse_items', 'premium_warehouse_orders', 'mystery'],
            'preserved_file_areas' => ['data/Premium_Warehouse'],
            'leftover_files' => ['data/Utils_FileStorage' => ['count' => 12, 'bytes' => 3400, 'sample' => ['data/Utils_FileStorage/a/b/c']]],
            'leftover_total' => ['count' => 12, 'bytes' => 3400],
        ];
        $map = ['premium_warehouse_items' => 'legacy_premium_warehouse_items', 'premium_warehouse_orders' => 'legacy_premium_warehouse_orders', 'mystery' => 'legacy_mystery'];

        $report = (new LeftBehindReport)->build($coverage, $verification, $map, $this->root, 'storage/app/migration-wizard/leftover-files.txt');

        $byName = [];
        foreach ($report['modules'] as $module) {
            $byName[(string) $module['legacy_module']] = $module;
        }
        self::assertSame(['Custom_Invoice', 'Premium_Warehouse'], array_keys($byName));

        $warehouse = $byName['Premium_Warehouse'];
        self::assertSame('modules/Premium/Warehouse', $warehouse['code_folder']);
        self::assertSame([['table' => 'premium_warehouse_items', 'legacy_table' => 'legacy_premium_warehouse_items', 'estimated_rows' => 120], ['table' => 'premium_warehouse_orders', 'legacy_table' => 'legacy_premium_warehouse_orders', 'estimated_rows' => 7]], $warehouse['tables']);
        self::assertSame([['folder' => 'data/Premium_Warehouse', 'files' => 4, 'bytes' => 900]], $warehouse['data_folders']);

        // A Premium/Custom module with only code still shows; a migrated Core module and the dropped-later tables do not.
        self::assertSame('modules/Custom/Invoice', $byName['Custom_Invoice']['code_folder']);
        self::assertSame([], $byName['Custom_Invoice']['tables']);
        self::assertArrayNotHasKey('CRM_Contacts', $byName);
        // Data nobody owns is not listed: it is removed right after verification.
        self::assertArrayNotHasKey('', $byName);

        self::assertSame(12, $report['unreferenced_files']['total']['count']);
        self::assertSame(3400, $report['unreferenced_files']['total']['bytes']);
        self::assertSame(['data/Utils_FileStorage'], array_keys($report['unreferenced_files']['areas']));
        self::assertSame('storage/app/migration-wizard/leftover-files.txt', $report['unreferenced_files']['list_file']);
        self::assertSame(LeftBehindReport::NOTES, $report['notes']);
        self::assertCount(2, $report['notes']);
        self::assertStringContainsString('legacy_', $report['notes'][0]);
        self::assertStringContainsString('legacy_id', $report['notes'][1]);
    }

    public function test_with_nothing_left_the_report_is_empty_but_still_carries_the_notes(): void
    {
        $report = (new LeftBehindReport)->build(['modules' => [], 'tables' => [], 'files' => []], [], [], $this->root);

        self::assertSame([], $report['modules']);
        self::assertSame([], $report['unreferenced_files']['areas']);
        self::assertNull($report['unreferenced_files']['list_file']);
        self::assertCount(2, $report['notes']);
    }
}
