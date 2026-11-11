<?php

namespace Tests\Unit;

use App\Services\LegacyImport\LegacyStockColumns;
use Epesi\Modules\MigrationWizard\Checks\AssessmentSummary;
use Epesi\Modules\MigrationWizard\Detection\ApprovedLegacySchemas;
use Epesi\Modules\MigrationWizard\Detection\LegacyModuleCatalog;
use Epesi\Modules\MigrationWizard\Detection\ModuleCoverage;
use Epesi\Modules\MigrationWizard\Detection\SchemaFingerprint;
use PHPUnit\Framework\TestCase;

/**
 * A legacy recordset with extra columns is migratable: each becomes a custom field. Only a missing
 * required column, more extras than a model can hold, or an extra on a table that cannot carry it block.
 */
class MigrationWizardRecordsetExtrasTest extends TestCase
{
    private const FIELDS = [
        ['field' => 'Account Manager', 'caption' => null, 'type' => 'select', 'param' => 'contact::Last Name|First Name;x;y', 'visible' => 1, 'required' => 0, 'filter' => 1, 'export' => 1, 'active' => 1, 'position' => 19, 'help' => null],
        ['field' => 'Invoice Email', 'caption' => '', 'type' => 'text', 'param' => '128', 'visible' => 0, 'required' => 0, 'filter' => 0, 'export' => 1, 'active' => 1, 'position' => 20, 'help' => null],
    ];

    private function report(array $companyColumns, array $extraTables = [], array $fields = self::FIELDS): array
    {
        $stock = LegacyStockColumns::COLUMNS;
        $columns = ['user_login' => $stock['user_login'], 'company_data_1' => $companyColumns, 'contact_data_1' => $stock['contact_data_1']] + $extraTables;
        $tables = array_map(static fn (array $list): array => ['columns' => $list, 'estimated_rows' => 5, 'estimated_bytes' => 100, 'engine' => 'InnoDB'], $columns);
        $inventory = [
            'version' => '1.9.1',
            'fingerprint' => (new SchemaFingerprint)->hash($columns),
            'modules' => [['name' => 'Base_User', 'version' => '0'], ['name' => 'CRM_Contacts', 'version' => '0'], ...(isset($extraTables['utils_filestorage']) ? [['name' => 'Utils_FileStorage', 'version' => '0']] : [])],
            'tables' => $tables,
            'files' => [],
            'record_fields' => ['company' => ['column_types' => [], 'fields' => $fields, 'callbacks' => ['Invoice Email' => ['CRM_ContactsCommon::display_email']]]],
        ];

        return (new ModuleCoverage)->report($inventory, (new LegacyModuleCatalog)->knownModules(), [], (new ApprovedLegacySchemas)->all(), ['CRM_Contacts' => ['companies', 'contacts'], 'Base_User' => ['users']]);
    }

    private static function module(array $report, string $name): array
    {
        return array_values(array_filter($report['modules'], static fn (array $m): bool => $m['legacy_module'] === $name))[0];
    }

    public function test_declared_extra_columns_do_not_block_and_are_planned_as_custom_fields(): void
    {
        $report = $this->report([...LegacyStockColumns::COLUMNS['company_data_1'], 'f_account_manager', 'f_invoice_email']);

        $this->assertSame([], $report['blocked_modules']);
        $module = self::module($report, 'CRM_Contacts');
        $this->assertTrue($module['schema_approved']);
        $this->assertTrue($module['migrated_by_import']);
        $this->assertSame(['companies', 'contacts'], $module['importer_tabs']);
        $fields = $report['custom_fields']['company']['fields'];
        $this->assertSame(['account_manager', 'invoice_email'], array_column($fields, 'name'));
        $this->assertSame(['relation', 'email'], array_column($fields, 'type'));
    }

    public function test_an_undeclared_extra_column_is_still_added_from_its_sql_type(): void
    {
        $report = $this->report([...LegacyStockColumns::COLUMNS['company_data_1'], 'f_mystery'], fields: []);

        $this->assertSame([], $report['blocked_modules']);
        $this->assertSame(['mystery'], array_column($report['custom_fields']['company']['fields'], 'name'));
        $this->assertFalse($report['custom_fields']['company']['fields'][0]['declared']);
    }

    public function test_a_missing_required_column_blocks_the_module_naming_it(): void
    {
        $report = $this->report(array_values(array_diff(LegacyStockColumns::COLUMNS['company_data_1'], ['created_on'])));

        $this->assertSame('required_column_missing', $report['blocked_modules'][0]['schema_differences'][0]['problem']);
        $this->assertSame(['created_on'], $report['blocked_modules'][0]['schema_differences'][0]['missing']);
        $this->assertFalse(self::module($report, 'CRM_Contacts')['migrated_by_import']);

        $summary = (new AssessmentSummary)->summarize(['legacy_signature' => true, 'compatible' => true, 'coverage' => $report, 'blocked_modules' => $report['blocked_modules']]);
        $this->assertStringContainsString('required column created_on is missing from the table company_data_1', $summary['blocking'][0]);
        $this->assertStringNotContainsString('usually fields added by other modules', $summary['blocking'][0]);
    }

    public function test_a_missing_optional_column_is_tolerated(): void
    {
        $report = $this->report(array_values(array_diff(LegacyStockColumns::COLUMNS['company_data_1'], ['f_tax_id'])));

        $this->assertSame([], $report['blocked_modules']);
    }

    public function test_more_extras_than_a_model_can_hold_block_the_module(): void
    {
        $extras = array_map(static fn (int $i): string => 'f_extra_'.$i, range(1, 65));
        $report = $this->report([...LegacyStockColumns::COLUMNS['company_data_1'], ...$extras], fields: []);

        $this->assertSame('too_many_extra_fields', $report['blocked_modules'][0]['schema_differences'][0]['problem']);
        $summary = (new AssessmentSummary)->summarize(['legacy_signature' => true, 'compatible' => true, 'coverage' => $report, 'blocked_modules' => $report['blocked_modules']]);
        $this->assertStringContainsString('65 extra fields, more than the 64 custom fields', $summary['blocking'][0]);
    }

    public function test_an_extra_column_on_a_table_that_cannot_carry_it_blocks_and_is_named(): void
    {
        $report = $this->report(LegacyStockColumns::COLUMNS['company_data_1'], [
            'utils_filestorage' => LegacyStockColumns::COLUMNS['utils_filestorage'],
            'utils_filestorage_files' => [...LegacyStockColumns::COLUMNS['utils_filestorage_files'], 'colour'],
        ]);

        $this->assertSame(['Utils_FileStorage'], array_column($report['blocked_modules'], 'legacy_module'));
        $this->assertSame(['colour'], $report['blocked_modules'][0]['schema_differences'][0]['extra']);
        $summary = (new AssessmentSummary)->summarize(['legacy_signature' => true, 'compatible' => true, 'coverage' => $report, 'blocked_modules' => $report['blocked_modules']]);
        $this->assertStringContainsString('extra column(s) colour', $summary['blocking'][0]);
    }

    public function test_the_summary_lists_the_fields_and_puts_the_module_under_can_be_migrated(): void
    {
        $report = $this->report([...LegacyStockColumns::COLUMNS['company_data_1'], 'f_account_manager', 'f_invoice_email']);
        $summaries = new AssessmentSummary;
        $summary = $summaries->summarize([
            'legacy_signature' => true, 'compatible' => true, 'coverage' => $report, 'blocked_modules' => [],
            'module_data' => ['modules' => [['name' => 'CRM_Contacts', 'version' => '0', 'kind' => 'Core Epesi', 'recordsets' => [['tab' => 'company', 'records' => 2, 'exact' => true]], 'tables' => []]], 'accounted_tables' => 1, 'unaccounted_tables' => 0],
        ]);

        $this->assertSame('Migrated', $summary['modules'][0]['action']);
        $this->assertSame('Contact link', $summary['custom_fields'][0]['fields'][0]['type_label']);
        $html = $summaries->html($summary);
        $this->assertStringContainsString('Fields that will be added during migration', $html);
        $this->assertStringContainsString('Company: Account Manager (legacy select)', $html);
        $this->assertStringContainsString('custom field ‘Account Manager’, Contact link', $html);
        $this->assertStringContainsString('‘Invoice Email’, Email', $html);
        $this->assertStringContainsString('Can be migrated (1)', $html);
    }

    public function test_watchdog_dashboard_and_acl_are_not_listed_at_all(): void
    {
        $module = static fn (string $name): array => ['name' => $name, 'version' => '0', 'kind' => 'Core Epesi', 'recordsets' => [], 'tables' => [['table' => $name.'_t', 'rows' => 3, 'exact' => true]]];
        $summaries = new AssessmentSummary;
        $summary = $summaries->summarize([
            'legacy_signature' => true, 'compatible' => true, 'coverage' => ['modules' => []], 'blocked_modules' => [],
            'table_count' => 10,
            'module_data' => ['modules' => [$module('Base_Acl'), $module('Base_Dashboard'), $module('Utils_Watchdog'), $module('Base_Setup'), $module('Premium_Import'), $module('Base_Something')], 'accounted_tables' => 4, 'unaccounted_tables' => 6],
        ]);

        $this->assertSame(['Base_Something'], array_column($summary['modules'], 'name'));
        $this->assertSame(1, $summary['module_count']);
        $html = $summaries->html($summary);
        foreach (['Base_Acl', 'Base_Dashboard', 'Utils_Watchdog'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html);
        }
        $this->assertStringContainsString('Base_Something', $html);
    }

    public function test_a_field_added_without_its_data_is_listed_separately(): void
    {
        $fields = [['field' => 'Scan', 'caption' => null, 'type' => 'file', 'param' => '', 'visible' => 0, 'required' => 0, 'filter' => 0, 'export' => 1, 'active' => 1, 'position' => 21, 'help' => null]];
        $report = $this->report([...LegacyStockColumns::COLUMNS['company_data_1'], 'f_scan'], fields: $fields);
        $summaries = new AssessmentSummary;
        $html = $summaries->html($summaries->summarize(['legacy_signature' => true, 'compatible' => true, 'coverage' => $report, 'blocked_modules' => []]));

        $this->assertStringContainsString('Fields whose data cannot be copied yet', $html);
        $this->assertStringContainsString('the legacy table is kept', $html);
    }
}
