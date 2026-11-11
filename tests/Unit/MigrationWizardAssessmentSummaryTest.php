<?php

use Epesi\Modules\MigrationWizard\Checks\AssessmentSummary;
use PHPUnit\Framework\TestCase;

/** The assessment is shown as a short list of the modules that own data, never table by table. */
class MigrationWizardAssessmentSummaryTest extends TestCase
{
    /** @return array<string, mixed> an assessment whose module_data lists core, Premium and Custom modules */
    public static function report(bool $compatible = true): array
    {
        return [
            'version' => '1.9.1',
            'table_count' => 60,
            'legacy_signature' => true,
            'compatible' => $compatible,
            'fingerprint' => str_repeat('a', 64),
            'blocked_modules' => [],
            'table_statistics' => [['table' => 'company_data_1', 'estimated_rows' => 120, 'estimated_bytes' => 1000, 'engine' => 'InnoDB']],
            'module_data' => [
                'installed_modules' => 7,
                'accounted_tables' => 41,
                'unaccounted_tables' => 19,
                'modules' => [
                    ['name' => 'Base_User', 'version' => '1', 'kind' => 'Core Epesi', 'recordsets' => [], 'tables' => [['table' => 'user_login', 'rows' => 10, 'exact' => true]]],
                    ['name' => 'CRM_Contacts', 'version' => '1', 'kind' => 'Core Epesi', 'recordsets' => [
                        ['tab' => 'company', 'records' => 120, 'exact' => true, 'via' => 'source'],
                        ['tab' => 'contact', 'records' => 300, 'exact' => true, 'via' => 'source'],
                    ], 'tables' => []],
                    ['name' => 'Custom_Invoice', 'version' => '1', 'kind' => 'Custom', 'recordsets' => [['tab' => 'custom_invoice', 'records' => 7, 'exact' => true, 'via' => 'name']], 'tables' => []],
                    ['name' => 'Premium_Invoice', 'version' => '2', 'kind' => 'Premium', 'recordsets' => [
                        ['tab' => 'premium_invoice', 'records' => 400000, 'exact' => false, 'via' => 'source'],
                        ['tab' => 'premium_invoice_related', 'records' => 0, 'exact' => true, 'via' => 'source'],
                    ], 'tables' => []],
                ],
            ],
            'coverage' => [
                'modules' => [
                    ['legacy_module' => 'CRM_Contacts', 'legacy_version' => '1', 'outcome' => 'supported', 'disposition' => 'candidate_for_import', 'schema_approved' => true],
                    ['legacy_module' => 'Base_User', 'legacy_version' => '1', 'outcome' => 'known_but_not_migrated', 'disposition' => 'preserve', 'schema_approved' => false],
                    ['legacy_module' => 'Premium_Invoice', 'legacy_version' => '2', 'outcome' => 'unknown', 'disposition' => 'preserve', 'schema_approved' => null],
                    ['legacy_module' => 'Base_About', 'legacy_version' => '1', 'outcome' => 'known_but_not_migrated', 'disposition' => 'preserve', 'schema_approved' => null],
                ],
            ],
            'execution_advice' => ['cli_recommended' => false, 'database_estimated_bytes' => 5000000, 'database_estimated_rows' => 481, 'files_scanned' => 12, 'file_bytes_scanned' => 2048, 'file_scan_incomplete' => false],
        ];
    }

    private static function byName(array $summary): array
    {
        return array_column($summary['modules'], null, 'name');
    }

    public function test_a_premium_module_read_by_a_registered_importer_is_migrated_and_other_premium_modules_are_not(): void
    {
        $report = self::report();
        $report['module_data']['modules'][] = ['name' => 'Premium_MultipleAddresses', 'version' => '1', 'kind' => 'Premium', 'recordsets' => [], 'tables' => [['table' => 'premium_multiple_addresses_data_1', 'rows' => 128, 'exact' => true]]];
        $report['coverage']['modules'][] = ['legacy_module' => 'Premium_MultipleAddresses', 'legacy_version' => '1', 'outcome' => 'unknown', 'disposition' => 'preserve', 'schema_approved' => null, 'importer_tabs' => ['contacts'], 'migrated_by_import' => true];
        // Flagged but with no importer reading it: still untouched.
        $report['coverage']['modules'][2]['migrated_by_import'] = true;

        $modules = self::byName((new AssessmentSummary)->summarize($report));

        $this->assertSame('Migrated', $modules['Premium_MultipleAddresses']['action']);
        $this->assertSame('Kept untouched (not migrated)', $modules['Premium_Invoice']['action']);
        $this->assertSame('Kept untouched (not migrated)', $modules['Custom_Invoice']['action']);
    }

    public function test_only_modules_with_data_are_listed_with_their_recordsets_tables_and_outcome(): void
    {
        $summary = (new AssessmentSummary)->summarize(self::report());
        $modules = self::byName($summary);

        $this->assertTrue($summary['approved']);
        $this->assertSame('1.9.1', $summary['version']);
        $this->assertSame(['Base_User', 'CRM_Contacts', 'Custom_Invoice', 'Premium_Invoice'], array_column($summary['modules'], 'name'));
        $this->assertSame(4, $summary['module_count']);
        $this->assertSame(5, $summary['recordset_count']);
        $this->assertArrayNotHasKey('Base_About', $modules, 'a module without tables or recordsets is not listed');
        $this->assertSame(['company', 'contact'], array_column($modules['CRM_Contacts']['recordsets'], 'tab'));
        $this->assertSame('Migrated', $modules['CRM_Contacts']['action']);
        $this->assertSame('Kept untouched (blocked, see above)', $modules['Base_User']['action']);
        $this->assertSame('Kept untouched (not migrated)', $modules['Premium_Invoice']['action']);
        $this->assertSame('Kept untouched (not migrated)', $modules['Custom_Invoice']['action']);
        $this->assertSame([41, 19], [$summary['accounted_tables'], $summary['unaccounted_tables']]);
        $this->assertStringContainsString('481 estimated rows', $summary['size']);
    }

    public function test_a_module_that_cannot_be_migrated_says_why_in_plain_words(): void
    {
        $report = self::report();
        $report['blocked_modules'] = [['legacy_module' => 'CRM_Contacts', 'schema_differences' => [['table' => 'company_data_1', 'problem' => 'columns_differ']]]];
        $summary = (new AssessmentSummary)->summarize($report);
        $html = (new AssessmentSummary)->html($summary);

        $this->assertStringContainsString('CRM_Contacts cannot be migrated: the table company_data_1 differs from standard Epesi 1.9.1', $summary['blocking'][0]);
        $this->assertStringContainsString('Its data is kept untouched.', $summary['blocking'][0]);
        $this->assertStringContainsString('Data can be migrated.', $html);
        $this->assertStringContainsString('CRM_Contacts cannot be migrated', $html);
        $this->assertStringContainsString('overflow-wrap:anywhere', $html, 'long text wraps inside the window');
        $this->assertStringContainsString('Can be migrated (', $html, 'modules are grouped by outcome');
        $this->assertStringContainsString('Will not be migrated (', $html);
        $this->assertStringContainsString('only when a corresponding module is found', $html);
        $this->assertStringNotContainsString('Kept untouched', $html);
        $this->assertLessThan(strpos($html, 'Premium_Invoice</td>'), strpos($html, 'Will not be migrated ('), 'an unmigrated module sits under its group');
    }

    public function test_an_unapproved_installation_states_why_and_migrates_nothing(): void
    {
        $report = self::report(false);
        $report['blocked_modules'] = [['legacy_module' => 'Utils_Calendar', 'schema_differences' => []]];
        $summary = (new AssessmentSummary)->summarize($report);

        $this->assertFalse($summary['approved']);
        $this->assertStringContainsString('not approved', $summary['blocking'][0]);
        $this->assertStringContainsString('Utils_Calendar', $summary['blocking'][1]);
        $this->assertSame('Kept untouched (not migrated yet)', self::byName($summary)['CRM_Contacts']['action']);
    }

    public function test_the_html_collapses_tables_into_recordsets_and_escapes_names(): void
    {
        $report = self::report();
        $report['module_data']['modules'][] = ['name' => 'Custom_<b>x', 'version' => '1', 'kind' => 'Custom', 'recordsets' => [['tab' => 'x<i>', 'records' => 1, 'exact' => true, 'via' => 'name']], 'tables' => []];
        $html = (new AssessmentSummary)->html((new AssessmentSummary)->summarize($report));

        $this->assertStringContainsString('CRM_Contacts', $html);
        $this->assertStringContainsString('company 120, contact 300', $html);
        $this->assertStringContainsString('1 table, 10 rows', $html, 'plain tables are summed, not listed by name');
        $this->assertStringNotContainsString('user_login', $html);
        $this->assertStringContainsString('white-space:nowrap', $html, 'the narrow columns do not wrap');
        $this->assertStringContainsString('premium_invoice ~400,000', $html, 'an estimate is marked');
        $this->assertStringContainsString('Custom_&lt;b&gt;x', $html);
        $this->assertStringNotContainsString('Custom_<b>x', $html);
        $this->assertStringContainsString('41 of 60 database tables belong to the modules above or to system modules that are not listed (settings, search index, dashboard, access rights and the like); 19 belong to no listed module', $html);
        $this->assertStringNotContainsString('Base_About', $html);
        foreach (['company_data_1', 'company_field', 'premium_invoice_edit_history', 'table_statistics'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
    }

    public function test_a_report_without_module_data_says_so_instead_of_listing_nothing(): void
    {
        $report = self::report();
        unset($report['module_data']);
        $summary = (new AssessmentSummary)->summarize($report);

        $this->assertFalse($summary['module_data_available']);
        $this->assertStringContainsString('could not be built', (new AssessmentSummary)->html($summary));
    }

    public function test_the_full_report_is_saved_without_credentials(): void
    {
        $directory = sys_get_temp_dir().'/assessment-summary-'.getmypid();
        $report = self::report();
        $report['db'] = ['DATABASE_PASSWORD' => 'hunter2', 'host' => 'h'];
        $path = (new AssessmentSummary)->save($report, $directory);

        $saved = json_decode(file_get_contents($path), true);
        $this->assertCount(1, $saved['table_statistics'], 'the full detail is kept for diagnostics');
        $this->assertStringNotContainsString('hunter2', file_get_contents($path));
        $txt = file_get_contents($directory.'/assessment.txt');
        $html = file_get_contents($directory.'/assessment.html');
        $this->assertStringContainsString('CAN BE MIGRATED (', $txt);
        $this->assertStringContainsString('WILL NOT BE MIGRATED (', $txt);
        $this->assertStringContainsString('FULL ASSESSMENT DATA', $txt);
        $this->assertStringContainsString('<!doctype html>', $html);
        $this->assertStringContainsString('Can be migrated (', $html);
        foreach ([$txt, $html] as $text) {
            $this->assertStringNotContainsString('hunter2', $text);
        }
        foreach (['json', 'txt', 'html'] as $extension) {
            unlink($directory.'/assessment.'.$extension);
        }
        rmdir($directory);
    }
}
