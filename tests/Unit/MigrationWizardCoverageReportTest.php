<?php

use Epesi\Modules\MigrationWizard\Detection\ModuleCoverage;
use Epesi\Modules\MigrationWizard\Detection\SchemaFingerprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MigrationWizardCoverageReportTest extends TestCase
{
    private static function withFingerprint(array $inventory): array
    {
        $inventory['fingerprint'] = (new SchemaFingerprint)->hash(array_map(
            static fn (array $table): array => $table['columns'], $inventory['tables']
        ));

        return $inventory;
    }

    private static function fixture(): array
    {
        return self::withFingerprint(require __DIR__.'/../Fixtures/migration-wizard-coverage-191.php');
    }

    /** Every known module of the fixture is approved for the shape it has in the fixture. */
    private static function approval(array $fixture): array
    {
        $modules = [];
        foreach ($fixture['known_modules'] as $module) {
            $shape = [];
            foreach ($module['source_tables'] as $table) {
                $shape[$table] = SchemaFingerprint::tableHash($fixture['tables'][$table]['columns']);
            }
            $modules[$module['legacy_module']] = [$shape];
        }

        return [['version' => '1.9.1', 'modules' => $modules]];
    }

    /** The module-level fingerprint a certification of $module has to name. */
    private static function moduleFingerprint(array $fixture, string $module = 'CRM_Contacts'): string
    {
        $known = array_column($fixture['known_modules'], null, 'legacy_module')[$module];

        return ModuleCoverage::moduleFingerprint($known['source_tables'], array_map(static fn (array $t): array => $t['columns'], $fixture['tables']));
    }

    /** A CRM_Contacts importer mapping every column except `indexed`, certified for $fixture. */
    private static function importer(array $fixture): array
    {
        $mapping = static fn (string $table): array => array_values(array_diff($fixture['tables'][$table]['columns'], ['indexed']));

        return [
            'id' => 'contacts-fixture',
            'legacy_module' => 'CRM_Contacts',
            'destination_module' => 'Epesi/CRM/Contacts',
            'source_tables' => ['company_data_1', 'contact_data_1'],
            'source_file_areas' => ['data/CRM_Contacts_Photo'],
            'required_columns' => [
                'company_data_1' => $mapping('company_data_1'),
                'contact_data_1' => $mapping('contact_data_1'),
            ],
            'ignored_columns' => ['company_data_1' => ['indexed'], 'contact_data_1' => ['indexed']],
            'certified_for' => [['version' => '1.9.1', 'fingerprint' => self::moduleFingerprint($fixture), 'module_version' => 1]],
        ];
    }

    private static function byKey(array $report, string $section, string $key): array
    {
        return array_column($report[$section], null, $key);
    }

    public function test_unknown_modules_tables_and_file_areas_are_preserved(): void
    {
        $fixture = self::fixture();
        $report = (new ModuleCoverage)->report($fixture, $fixture['known_modules']);
        self::assertTrue($report['assessment_only']);
        self::assertFalse($report['schema_approved']);
        self::assertSame(['custom_invoice'], $report['unclassified_tables']);
        self::assertSame(['data/Custom_Invoice', 'data/orphaned'], $report['unclassified_file_areas']);
        $modules = self::byKey($report, 'modules', 'legacy_module');
        self::assertSame('unknown', $modules['Custom_Invoice']['outcome']);
        self::assertSame('preserve', $modules['Custom_Invoice']['disposition']);
        self::assertSame('known_but_not_migrated', $modules['CRM_Contacts']['outcome']);
        self::assertSame(['company_data_1', 'contact_data_1'], $modules['CRM_Contacts']['tables']);
        self::assertSame(['data/CRM_Contacts_Photo'], $modules['CRM_Contacts']['file_areas']);
        self::assertSame(5, $modules['CRM_Contacts']['estimated_rows']);
        self::assertCount(count($fixture['tables']), $report['tables']);
        self::assertCount(count($fixture['files']), $report['files']);
        foreach ($report['tables'] as $table) {
            self::assertSame('preserve', $table['disposition']);
            self::assertSame($table['columns'], $table['unmapped_columns']);
        }
    }

    public function test_fully_mapped_certified_module_on_approved_schema_is_supported(): void
    {
        $fixture = self::fixture();
        $report = (new ModuleCoverage)->report($fixture, $fixture['known_modules'], [self::importer($fixture)], self::approval($fixture));
        $modules = self::byKey($report, 'modules', 'legacy_module');
        $tables = self::byKey($report, 'tables', 'table');
        self::assertSame('supported', $modules['CRM_Contacts']['outcome']);
        self::assertTrue($modules['CRM_Contacts']['importer_certified']);
        self::assertSame('supported', $tables['contact_data_1']['outcome']);
        self::assertSame([], $tables['contact_data_1']['unmapped_columns']);
        self::assertSame('candidate_for_import', $tables['company_data_1']['disposition']);
        self::assertSame('supported', self::byKey($report, 'files', 'area')['data/CRM_Contacts_Photo']['outcome']);
        self::assertSame('unknown', $tables['custom_invoice']['outcome']);
        self::assertSame('known_but_not_migrated', $tables['task_data_1']['outcome']);
        self::assertSame('preserve', self::byKey($report, 'files', 'area')['data/orphaned']['disposition']);
        self::assertFalse($report['import_coverage_verified']);
    }

    public static function unapprovedSchemas(): array
    {
        return [
            'no approval' => [static fn (array $f): array => []],
            'wrong version' => [static fn (array $f): array => [['version' => '1.9.0', 'modules' => self::approval($f)[0]['modules']]]],
            'version only' => [static fn (array $f): array => [['version' => '1.9.1']]],
            'old whole-database fingerprint' => [static fn (array $f): array => [['version' => '1.9.1', 'fingerprint' => $f['fingerprint']]]],
            'unknown version' => [static fn (array $f): array => [['version' => 'unknown', 'modules' => self::approval($f)[0]['modules']]]],
        ];
    }

    #[DataProvider('unapprovedSchemas')]
    public function test_unapproved_schema_preserves_everything(Closure $approvals): void
    {
        $fixture = self::fixture();
        $report = (new ModuleCoverage)->report($fixture, $fixture['known_modules'], [self::importer($fixture)], $approvals($fixture));
        self::assertTrue($report['assessment_only']);
        foreach (['modules', 'tables', 'files'] as $section) {
            foreach ($report[$section] as $row) {
                self::assertNotSame('supported', $row['outcome']);
                self::assertSame('preserve', $row['disposition']);
            }
        }
    }

    public static function invalidCertifications(): array
    {
        return [
            'legacy boolean flag' => [static fn (array $f): mixed => null, ['certified' => true]],
            'empty' => [static fn (array $f): array => [], []],
            'other version' => [static fn (array $f): array => [['version' => '1.9.0', 'fingerprint' => self::moduleFingerprint($f), 'module_version' => 1]], []],
            'whole-database fingerprint' => [static fn (array $f): array => [['version' => '1.9.1', 'fingerprint' => $f['fingerprint'], 'module_version' => 1]], []],
            'other fingerprint' => [static fn (array $f): array => [['version' => '1.9.1', 'fingerprint' => str_repeat('a', 64), 'module_version' => 1]], []],
            'other module version' => [static fn (array $f): array => [['version' => '1.9.1', 'fingerprint' => self::moduleFingerprint($f), 'module_version' => 2]], []],
            'no module version' => [static fn (array $f): array => [['version' => '1.9.1', 'fingerprint' => self::moduleFingerprint($f)]], []],
            'truthy string' => [static fn (array $f): array => ['true'], []],
        ];
    }

    #[DataProvider('invalidCertifications')]
    public function test_certification_must_match_exact_version_fingerprint_and_module_version(Closure $certifiedFor, array $extra): void
    {
        $fixture = self::fixture();
        $importer = [...self::importer($fixture), ...$extra];
        $certification = $certifiedFor($fixture);
        if ($certification === null) {
            unset($importer['certified_for']);
        } else {
            $importer['certified_for'] = $certification;
        }
        $report = (new ModuleCoverage)->report($fixture, $fixture['known_modules'], [$importer], self::approval($fixture));
        $module = self::byKey($report, 'modules', 'legacy_module')['CRM_Contacts'];
        self::assertFalse($module['importer_certified']);
        self::assertSame('known_but_not_migrated', $module['outcome']);
        self::assertSame('preserve', self::byKey($report, 'tables', 'table')['contact_data_1']['disposition']);
        self::assertSame('preserve', self::byKey($report, 'files', 'area')['data/CRM_Contacts_Photo']['disposition']);
    }

    public function test_unmapped_columns_keep_table_preserved_and_are_listed(): void
    {
        $fixture = self::fixture();
        $importer = self::importer($fixture);
        $importer['required_columns']['contact_data_1'] = ['id', 'active'];
        unset($importer['ignored_columns']['contact_data_1']);
        $report = (new ModuleCoverage)->report($fixture, $fixture['known_modules'], [$importer], self::approval($fixture));
        $table = self::byKey($report, 'tables', 'table')['contact_data_1'];
        self::assertSame('known_but_not_migrated', $table['outcome']);
        self::assertSame('preserve', $table['disposition']);
        self::assertContains('f_first_name', $table['unmapped_columns']);
        self::assertContains('indexed', $table['unmapped_columns']);
        self::assertNotContains('id', $table['unmapped_columns']);
        self::assertSame('supported', self::byKey($report, 'tables', 'table')['company_data_1']['outcome']);
        self::assertSame('known_but_not_migrated', self::byKey($report, 'modules', 'legacy_module')['CRM_Contacts']['outcome']);
    }

    public function test_missing_required_column_keeps_table_and_module_uncertified(): void
    {
        $fixture = self::fixture();
        $importer = self::importer($fixture);
        $importer['required_columns']['contact_data_1'][] = 'does_not_exist';
        $report = (new ModuleCoverage)->report($fixture, $fixture['known_modules'], [$importer], self::approval($fixture));
        $tables = self::byKey($report, 'tables', 'table');
        self::assertSame(['does_not_exist'], $tables['contact_data_1']['missing_columns']);
        self::assertSame('preserve', $tables['contact_data_1']['disposition']);
        self::assertSame('supported', $tables['company_data_1']['outcome']);
        self::assertSame('known_but_not_migrated', self::byKey($report, 'modules', 'legacy_module')['CRM_Contacts']['outcome']);
    }

    public function test_missing_declared_file_area_keeps_module_uncertified(): void
    {
        $fixture = self::fixture();
        unset($fixture['files']['data/CRM_Contacts_Photo']);
        $report = (new ModuleCoverage)->report($fixture, $fixture['known_modules'], [self::importer($fixture)], self::approval($fixture));
        self::assertSame('supported', self::byKey($report, 'tables', 'table')['contact_data_1']['outcome']);
        self::assertSame('known_but_not_migrated', self::byKey($report, 'modules', 'legacy_module')['CRM_Contacts']['outcome']);
    }

    public function test_table_with_no_mapped_columns_is_not_supported(): void
    {
        $fixture = self::fixture();
        $importer = self::importer($fixture);
        $importer['required_columns']['contact_data_1'] = [];
        $importer['ignored_columns']['contact_data_1'] = $fixture['tables']['contact_data_1']['columns'];
        $report = (new ModuleCoverage)->report($fixture, $fixture['known_modules'], [$importer], self::approval($fixture));
        self::assertSame('preserve', self::byKey($report, 'tables', 'table')['contact_data_1']['disposition']);
        self::assertSame('known_but_not_migrated', self::byKey($report, 'modules', 'legacy_module')['CRM_Contacts']['outcome']);
    }

    public function test_partial_importer_claim_keeps_unclaimed_table_and_module_preserved(): void
    {
        $fixture = self::fixture();
        $importer = self::importer($fixture);
        $importer['source_tables'] = ['contact_data_1'];
        unset($importer['required_columns']['company_data_1'], $importer['ignored_columns']['company_data_1']);
        $report = (new ModuleCoverage)->report($fixture, $fixture['known_modules'], [$importer], self::approval($fixture));
        $tables = self::byKey($report, 'tables', 'table');
        self::assertSame('supported', $tables['contact_data_1']['outcome']);
        self::assertSame('known_but_not_migrated', $tables['company_data_1']['outcome']);
        self::assertNull($tables['company_data_1']['importer']);
        self::assertSame('known_but_not_migrated', self::byKey($report, 'modules', 'legacy_module')['CRM_Contacts']['outcome']);
    }

    public function test_known_module_missing_from_registry_does_not_own_its_tables(): void
    {
        $fixture = self::fixture();
        $fixture['modules'] = array_values(array_filter($fixture['modules'], static fn (array $row): bool => $row['name'] !== 'CRM_Contacts'));
        $report = (new ModuleCoverage)->report($fixture, $fixture['known_modules'], [self::importer($fixture)], self::approval($fixture));
        $tables = self::byKey($report, 'tables', 'table');
        self::assertSame('unknown', $tables['contact_data_1']['outcome']);
        self::assertSame('preserve', $tables['contact_data_1']['disposition']);
        self::assertContains('company_data_1', $report['unclassified_tables']);
        self::assertContains('data/CRM_Contacts_Photo', $report['unclassified_file_areas']);
        self::assertArrayNotHasKey('CRM_Contacts', self::byKey($report, 'modules', 'legacy_module'));
    }

    public function test_module_without_assets_is_never_supported(): void
    {
        $fixture = self::fixture();
        $fixture['modules'][] = ['name' => 'CRM_Empty', 'version' => 1];
        $fixture['known_modules'][] = ['legacy_module' => 'CRM_Empty', 'destination_module' => 'Epesi/CRM/Empty', 'source_tables' => [], 'source_file_areas' => []];
        $importer = [
            'id' => 'empty', 'legacy_module' => 'CRM_Empty', 'destination_module' => 'Epesi/CRM/Empty',
            'source_tables' => [], 'source_file_areas' => [],
            'certified_for' => [['version' => '1.9.1', 'fingerprint' => self::moduleFingerprint($fixture, 'CRM_Empty'), 'module_version' => 1]],
        ];
        $report = (new ModuleCoverage)->report($fixture, $fixture['known_modules'], [$importer], self::approval($fixture));
        $module = self::byKey($report, 'modules', 'legacy_module')['CRM_Empty'];
        // A module that owns no table has no shape to approve, so it is never approved or certified.
        self::assertFalse($module['schema_approved']);
        self::assertFalse($module['importer_certified']);
        self::assertSame('known_but_not_migrated', $module['outcome']);
        self::assertSame('preserve', $module['disposition']);
    }

    public function test_numeric_table_area_and_module_names_are_classified(): void
    {
        $fixture = self::fixture();
        $fixture['tables']['123'] = ['columns' => ['id', 'value']];
        $fixture['files']['456'] = ['count' => 1, 'bytes' => 8];
        $fixture['modules'][] = ['name' => '42', 'version' => 3];
        $fixture['known_modules'][] = ['legacy_module' => '42', 'destination_module' => 'Custom/FortyTwo', 'source_tables' => ['123'], 'source_file_areas' => ['456']];
        $fixture = self::withFingerprint($fixture);
        $importer = [
            'id' => 'forty-two', 'legacy_module' => '42', 'destination_module' => 'Custom/FortyTwo',
            'source_tables' => ['123'], 'source_file_areas' => ['456'],
            'required_columns' => ['123' => ['id', 'value']],
            'certified_for' => [['version' => '1.9.1', 'fingerprint' => self::moduleFingerprint($fixture, '42'), 'module_version' => '3']],
        ];
        $report = (new ModuleCoverage)->report($fixture, $fixture['known_modules'], [$importer], self::approval($fixture));
        $module = self::byKey($report, 'modules', 'legacy_module')['42'];
        self::assertSame('42', $module['legacy_module']);
        self::assertSame(['123'], $module['tables']);
        self::assertSame(['456'], $module['file_areas']);
        self::assertSame('supported', $module['outcome']);
        self::assertSame('123', self::byKey($report, 'tables', 'table')['123']['table']);
    }

    public static function invalidDeclarations(): array
    {
        $importer = static fn (array $f, array $changes): array => [...self::importer($f), ...$changes];

        return [
            'missing inventory key' => [static function (array $f): array {
                unset($f['files']);

                return [$f, $f['known_modules'], []];
            }],
            'malformed fingerprint' => [static fn (array $f): array => [[...$f, 'fingerprint' => 'abc'], $f['known_modules'], []]],
            'fingerprint does not match columns' => [static function (array $f): array {
                $f['tables']['contact_data_1']['columns'][] = 'cf_123';

                return [$f, $f['known_modules'], []];
            }],
            'duplicate registry row' => [static fn (array $f): array => [[...$f, 'modules' => [...$f['modules'], ['name' => 'Base', 'version' => 2]]], $f['known_modules'], []]],
            'registry row without version' => [static fn (array $f): array => [[...$f, 'modules' => [...$f['modules'], ['name' => 'Other']]], $f['known_modules'], []]],
            'duplicate known module' => [static fn (array $f): array => [$f, [...$f['known_modules'], $f['known_modules'][0]], []]],
            'ambiguous table ownership' => [static fn (array $f): array => [$f, [...$f['known_modules'], ['legacy_module' => 'Custom_Invoice', 'destination_module' => 'X', 'source_tables' => ['contact_data_1']]], []]],
            'ambiguous file ownership' => [static fn (array $f): array => [$f, [...$f['known_modules'], ['legacy_module' => 'Custom_Invoice', 'destination_module' => 'X', 'source_file_areas' => ['data/CRM_Contacts_Photo']]], []]],
            'importer for unknown module' => [static fn (array $f): array => [$f, $f['known_modules'], [$importer($f, ['legacy_module' => 'Custom_Invoice'])]]],
            'importer destination mismatch' => [static fn (array $f): array => [$f, $f['known_modules'], [$importer($f, ['destination_module' => 'Epesi/CRM/Companies'])]]],
            'two importers for one module' => [static fn (array $f): array => [$f, $f['known_modules'], [$importer($f, []), $importer($f, ['id' => 'other'])]]],
            'duplicate importer id' => [static fn (array $f): array => [$f, $f['known_modules'], [$importer($f, []), [
                'id' => 'contacts-fixture', 'legacy_module' => 'CRM_Tasks', 'destination_module' => 'Epesi/CRM/Tasks',
                'source_tables' => ['task_data_1'], 'source_file_areas' => [],
            ]]]],
            'importer claims table outside module' => [static fn (array $f): array => [$f, $f['known_modules'], [$importer($f, ['source_tables' => ['contact_data_1', 'task_data_1']])]]],
            'importer claims file area outside module' => [static fn (array $f): array => [$f, $f['known_modules'], [$importer($f, ['source_file_areas' => ['data/orphaned']])]]],
            'columns mapped for unclaimed table' => [static fn (array $f): array => [$f, $f['known_modules'], [$importer($f, ['required_columns' => ['task_data_1' => ['id']]])]]],
            'column both mapped and ignored' => [static fn (array $f): array => [$f, $f['known_modules'], [$importer($f, ['ignored_columns' => ['contact_data_1' => ['id']]])]]],
            'certification not a list' => [static fn (array $f): array => [$f, $f['known_modules'], [$importer($f, ['certified_for' => true])]]],
        ];
    }

    #[DataProvider('invalidDeclarations')]
    public function test_invalid_declarations_fail_closed(Closure $arguments): void
    {
        [$inventory, $known, $importers] = $arguments(self::fixture());
        $this->expectException(InvalidArgumentException::class);
        (new ModuleCoverage)->report($inventory, $known, $importers);
    }

    public function test_real_1_9_1_schema_needs_full_column_coverage(): void
    {
        $sql = file_get_contents(__DIR__.'/../Fixtures/legacy-1.9.1-schema.sql');
        preg_match_all('/^CREATE TABLE `([^`]+)` \((.*?)^\)/ms', $sql, $blocks, PREG_SET_ORDER);
        $tables = [];
        foreach ($blocks as [, $table, $body]) {
            preg_match_all('/^\s+`([^`]+)`/m', $body, $columns);
            $tables[$table] = ['columns' => $columns[1]];
        }
        self::assertGreaterThan(100, count($tables));
        $inventory = self::withFingerprint([
            'version' => '1.9.1',
            'modules' => [['name' => 'CRM_Contacts', 'version' => 1]],
            'tables' => $tables,
            'files' => [],
        ]);
        $known = [['legacy_module' => 'CRM_Contacts', 'destination_module' => 'Epesi/CRM/Contacts', 'source_tables' => ['company_data_1', 'contact_data_1'], 'source_file_areas' => []]];
        $importer = [
            'id' => 'contacts', 'legacy_module' => 'CRM_Contacts', 'destination_module' => 'Epesi/CRM/Contacts',
            'source_tables' => ['company_data_1', 'contact_data_1'], 'source_file_areas' => [],
            'required_columns' => ['company_data_1' => ['id', 'active'], 'contact_data_1' => ['id', 'active']],
            'certified_for' => [['version' => '1.9.1', 'fingerprint' => ModuleCoverage::moduleFingerprint(['company_data_1', 'contact_data_1'], array_map(static fn (array $t): array => $t['columns'], $tables)), 'module_version' => 1]],
        ];
        $approval = [['version' => '1.9.1', 'modules' => ['CRM_Contacts' => [[
            'company_data_1' => SchemaFingerprint::tableHash($tables['company_data_1']['columns']),
            'contact_data_1' => SchemaFingerprint::tableHash($tables['contact_data_1']['columns']),
        ]]]]];

        $partial = (new ModuleCoverage)->report($inventory, $known, [$importer], $approval);
        $contacts = self::byKey($partial, 'tables', 'table')['contact_data_1'];
        self::assertSame('preserve', $contacts['disposition']);
        self::assertContains('f_last_name', $contacts['unmapped_columns']);
        self::assertSame('known_but_not_migrated', self::byKey($partial, 'modules', 'legacy_module')['CRM_Contacts']['outcome']);
        self::assertCount(count($tables) - 2, $partial['unclassified_tables']);

        foreach (['company_data_1', 'contact_data_1'] as $table) {
            $importer['required_columns'][$table] = $tables[$table]['columns'];
        }
        $full = (new ModuleCoverage)->report($inventory, $known, [$importer], $approval);
        self::assertSame('supported', self::byKey($full, 'modules', 'legacy_module')['CRM_Contacts']['outcome']);
        foreach ($full['tables'] as $row) {
            $expected = in_array($row['table'], ['company_data_1', 'contact_data_1'], true) ? 'candidate_for_import' : 'preserve';
            self::assertSame($expected, $row['disposition'], $row['table']);
        }
    }
}
