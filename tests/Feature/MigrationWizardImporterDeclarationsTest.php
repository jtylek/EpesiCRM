<?php

use Epesi\Modules\MigrationWizard\Detection\ApprovedLegacySchemas;
use Epesi\Modules\MigrationWizard\Detection\ImporterDeclarations;
use Epesi\Modules\MigrationWizard\Detection\LegacyInstallation;
use Epesi\Modules\MigrationWizard\Detection\LegacyModuleCatalog;
use Epesi\Modules\MigrationWizard\Detection\ModuleCoverage;
use Epesi\Modules\MigrationWizard\Detection\SchemaFingerprint;
use Epesi\Modules\MigrationWizard\Import\CertifiableImporter;
use Epesi\Modules\MigrationWizard\Import\LegacyImporter;
use Epesi\Modules\MigrationWizard\Import\LegacyImporterRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** A configurable, complete importer declaration (users by default) for the aggregator tests. */
final class DeclarationStubImporter implements CertifiableImporter
{
    /** @param  array<string, mixed>  $o */
    public function __construct(private array $o = []) {}

    public function id(): string
    {
        return $this->o['id'] ?? 'users';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function sourceTables(): array
    {
        return $this->o['source_tables'] ?? ['user_login'];
    }

    public function sourceTable(): string
    {
        return $this->sourceTables()[0];
    }

    public function sourceFileAreas(): array
    {
        return [];
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

    public function legacyModule(): string
    {
        return $this->o['legacy_module'] ?? 'Base_User';
    }

    public function requiredColumns(): array
    {
        return $this->o['required_columns'] ?? ['user_login' => ['id', 'login']];
    }

    public function ignoredColumns(): array
    {
        return $this->o['ignored_columns'] ?? [];
    }

    public function certifiedFor(): array
    {
        return $this->o['certified_for'] ?? [];
    }
}

final class MigrationWizardImporterDeclarationsTest extends TestCase
{
    /** @return array<string, list<string>> table => columns of the real 1.9.1 schema fixture */
    private static function schema(): array
    {
        $sql = file_get_contents(dirname(__DIR__).'/Fixtures/legacy-1.9.1-schema.sql');
        preg_match_all('/^CREATE TABLE `([^`]+)` \((.*?)^\)/ms', $sql, $blocks, PREG_SET_ORDER);
        $tables = [];
        foreach ($blocks as [, $table, $body]) {
            preg_match_all('/^\s+`([^`]+)`/m', $body, $columns);
            $tables[$table] = $columns[1];
        }

        return $tables;
    }

    private static function inventory(): array
    {
        $tables = self::schema();

        return [
            'version' => '1.9.1',
            'fingerprint' => (new SchemaFingerprint)->hash($tables),
            'modules' => [['name' => 'Base_User', 'version' => 1], ['name' => 'CRM_Contacts', 'version' => 1]],
            'tables' => array_map(static fn (array $columns): array => ['columns' => $columns, 'estimated_rows' => 0, 'estimated_bytes' => 0], $tables),
            'files' => [],
        ];
    }

    public function test_the_approved_module_shapes_are_the_ones_of_the_real_191_schema_fixture(): void
    {
        $approved = (new ApprovedLegacySchemas)->all();
        $schema = self::schema();

        self::assertCount(1, $approved);
        self::assertSame('1.9.1', $approved[0]['version']);
        self::assertCount(268, $schema);
        foreach (ApprovedLegacySchemas::EPESI_191_STOCK as $module => $tables) {
            foreach ($tables as $table => $hash) {
                self::assertSame(SchemaFingerprint::tableHash($schema[$table]), $hash, "{$module}.{$table}");
            }
        }
        // The patched mail layout is the stock shape plus rc_mails_attachments.file_id, nothing else.
        self::assertSame(SchemaFingerprint::tableHash([...$schema['rc_mails_attachments'], 'file_id']), ApprovedLegacySchemas::CRM_MAIL_PATCHED_ATTACHMENTS);
        self::assertCount(2, $approved[0]['modules']['CRM_Mail']);
        self::assertCount(1, $approved[0]['modules']['Base_User']);
    }

    public function test_every_catalog_module_has_an_approved_shape_over_exactly_its_tables(): void
    {
        foreach ((new LegacyModuleCatalog)->knownModules() as $module) {
            $tables = ApprovedLegacySchemas::EPESI_191_STOCK[$module['legacy_module']] ?? null;
            self::assertNotNull($tables, $module['legacy_module']);
            self::assertEqualsCanonicalizing($module['source_tables'], array_keys($tables), $module['legacy_module']);
        }
    }

    public function test_catalog_tables_exist_in_the_real_schema(): void
    {
        $schema = self::schema();
        foreach ((new LegacyModuleCatalog)->knownModules() as $module) {
            foreach ($module['source_tables'] as $table) {
                self::assertArrayHasKey($table, $schema);
            }
        }
    }

    public function test_registered_importers_declare_coverage_and_none_is_skipped(): void
    {
        $declarations = new ImporterDeclarations;
        $built = $declarations->build(app(LegacyImporterRegistry::class)->all(), (new LegacyModuleCatalog)->knownModules());

        self::assertSame([], $declarations->skipped);
        $byModule = array_column($built, null, 'legacy_module');
        self::assertSame('users', $byModule['Base_User']['id']);
        self::assertSame(['user_login'], $byModule['Base_User']['source_tables']);
        // Companies and Contacts share CRM_Contacts and are merged into one declaration.
        self::assertSame(['company_data_1', 'contact_data_1'], $byModule['CRM_Contacts']['source_tables']);
        self::assertSame('Epesi/CRM/Contacts', $byModule['CRM_Contacts']['destination_module']);
        self::assertStringContainsString('companies', $byModule['CRM_Contacts']['id']);
        self::assertStringContainsString('contacts', $byModule['CRM_Contacts']['id']);
        // No importer is certified yet, so the module stays uncertified.
        self::assertSame([], $byModule['CRM_Contacts']['certified_for']);
    }

    public function test_the_real_schema_is_classified_not_all_unclassified_and_nothing_is_supported(): void
    {
        $declarations = new ImporterDeclarations;
        $known = (new LegacyModuleCatalog)->knownModules();
        $importers = $declarations->build(app(LegacyImporterRegistry::class)->all(), $known);
        $inventory = self::inventory();

        $report = (new ModuleCoverage)->report($inventory, $known, $importers, (new ApprovedLegacySchemas)->all());

        self::assertTrue($report['schema_approved']);
        $tables = array_column($report['tables'], null, 'table');
        foreach (['user_login', 'company_data_1', 'contact_data_1'] as $table) {
            self::assertSame('known_but_not_migrated', $tables[$table]['outcome'], $table);
            self::assertSame('preserve', $tables[$table]['disposition']);
            self::assertNotNull($tables[$table]['importer']);
        }
        self::assertContains('f_phone', $tables['company_data_1']['unmapped_columns']);
        self::assertSame([], $tables['company_data_1']['missing_columns']);
        self::assertNotContains('user_login', $report['unclassified_tables']);
        self::assertNotContains('company_data_1', $report['unclassified_tables']);
        self::assertCount(count(self::schema()) - 3, $report['unclassified_tables']);
        foreach ($report['tables'] as $row) {
            self::assertNotSame('supported', $row['outcome']);
        }
    }

    public function test_an_unapproved_schema_is_still_assessment_only(): void
    {
        $known = (new LegacyModuleCatalog)->knownModules();
        $importers = (new ImporterDeclarations)->build(app(LegacyImporterRegistry::class)->all(), $known);
        $inventory = self::inventory();
        $inventory['version'] = '1.9.2';

        $report = (new ModuleCoverage)->report($inventory, $known, $importers, (new ApprovedLegacySchemas)->all());

        self::assertFalse($report['schema_approved']);
        self::assertTrue($report['assessment_only']);
        foreach ($report['modules'] as $module) {
            self::assertFalse($module['schema_approved']);
        }
    }

    /** @param  array<string, mixed>  $inventory */
    private static function report(array $inventory): array
    {
        $inventory['fingerprint'] = (new SchemaFingerprint)->hash(array_map(static fn (array $t): array => $t['columns'], $inventory['tables']));

        return (new ModuleCoverage)->report($inventory, (new LegacyModuleCatalog)->knownModules(), [], (new ApprovedLegacySchemas)->all());
    }

    public function test_premium_custom_and_unknown_tables_and_modules_do_not_make_the_installation_unapproved(): void
    {
        $inventory = self::inventory();
        $inventory['modules'][] = ['name' => 'Premium_Warehouse_Items', 'version' => 3];
        $inventory['modules'][] = ['name' => 'Custom_Thing', 'version' => 1];
        foreach (range(1, 470) as $i) {
            $inventory['tables']['premium_warehouse_'.$i.'_data_1'] = ['columns' => ['id', 'f_name', 'f_cf_extra'], 'estimated_rows' => 5, 'estimated_bytes' => 1];
        }
        $inventory['tables']['custom_thing'] = ['columns' => ['id'], 'estimated_rows' => 1, 'estimated_bytes' => 1];

        $report = self::report($inventory);

        self::assertTrue($report['schema_approved']);
        self::assertSame([], $report['blocked_modules']);
        $modules = array_column($report['modules'], null, 'legacy_module');
        self::assertTrue($modules['Base_User']['schema_approved']);
        self::assertTrue($modules['CRM_Contacts']['schema_approved']);
        self::assertNull($modules['Premium_Warehouse_Items']['schema_approved']);
        self::assertSame('unknown', $modules['Premium_Warehouse_Items']['outcome']);
        self::assertContains('premium_warehouse_470_data_1', $report['unclassified_tables']);
        self::assertContains('custom_thing', $report['unclassified_tables']);
        foreach ($report['tables'] as $row) {
            self::assertSame('preserve', $row['disposition']);
        }
    }

    public function test_an_extra_column_on_a_recordset_table_becomes_a_custom_field_and_does_not_block(): void
    {
        $inventory = self::inventory();
        $inventory['tables']['contact_data_1']['columns'][] = 'f_cf_my_field';

        $report = self::report($inventory);

        self::assertTrue($report['schema_approved']);
        $modules = array_column($report['modules'], null, 'legacy_module');
        self::assertTrue($modules['CRM_Contacts']['schema_approved']);
        self::assertSame([], $report['blocked_modules']);
        self::assertSame(['cf_my_field'], array_column($report['custom_fields']['contact']['fields'], 'name'));
    }

    public function test_an_extra_column_on_a_table_that_cannot_carry_it_blocks_only_that_module_and_is_named(): void
    {
        $inventory = self::inventory();
        $inventory['tables']['user_login']['columns'][] = 'colour';

        $report = self::report($inventory);

        self::assertTrue($report['schema_approved']);
        $modules = array_column($report['modules'], null, 'legacy_module');
        self::assertFalse($modules['Base_User']['schema_approved']);
        self::assertSame([['table' => 'user_login', 'problem' => 'columns_differ', 'missing' => [], 'extra' => ['colour']]], $modules['Base_User']['schema_differences']);
        self::assertTrue($modules['CRM_Contacts']['schema_approved']);
        self::assertSame(['Base_User'], array_column($report['blocked_modules'], 'legacy_module'));
    }

    public function test_a_missing_core_table_blocks_that_module(): void
    {
        $inventory = self::inventory();
        unset($inventory['tables']['company_data_1']);

        $modules = array_column(self::report($inventory)['modules'], null, 'legacy_module');

        self::assertFalse($modules['CRM_Contacts']['schema_approved']);
        self::assertSame([['table' => 'company_data_1', 'problem' => 'missing']], $modules['CRM_Contacts']['schema_differences']);
    }

    public function test_mail_is_approved_for_the_stock_and_for_the_patched_attachment_table_only(): void
    {
        $inventory = self::inventory();
        $inventory['modules'][] = ['name' => 'CRM_Mail', 'version' => 1];
        $mail = static fn (array $report): array => array_column($report['modules'], null, 'legacy_module')['CRM_Mail'];

        self::assertTrue($mail(self::report($inventory))['schema_approved']);

        $inventory['tables']['rc_mails_attachments']['columns'][] = 'file_id';
        self::assertTrue($mail(self::report($inventory))['schema_approved']);

        $inventory['tables']['rc_mails_attachments']['columns'][] = 'something_else';
        $blocked = $mail(self::report($inventory));
        self::assertFalse($blocked['schema_approved']);
        self::assertSame([['table' => 'rc_mails_attachments', 'problem' => 'columns_differ', 'missing' => [], 'extra' => ['file_id', 'something_else']]], $blocked['schema_differences']);
    }

    public function test_a_registered_known_module_with_no_approved_shape_is_not_approved(): void
    {
        $inventory = self::inventory();
        $inventory['modules'][] = ['name' => 'Test_Module', 'version' => 1];
        $known = [...(new LegacyModuleCatalog)->knownModules(), ['legacy_module' => 'Test_Module', 'destination_module' => 'Epesi/Test', 'source_tables' => ['company_field'], 'source_file_areas' => []]];
        $inventory['fingerprint'] = (new SchemaFingerprint)->hash(array_map(static fn (array $t): array => $t['columns'], $inventory['tables']));

        $report = (new ModuleCoverage)->report($inventory, $known, [], (new ApprovedLegacySchemas)->all());

        $tasks = array_column($report['modules'], null, 'legacy_module')['Test_Module'];
        self::assertFalse($tasks['schema_approved']);
        self::assertSame([['table' => 'company_field', 'problem' => 'no_approved_shape']], $tasks['schema_differences']);
    }

    public function test_an_importer_without_a_declaration_is_left_out_and_unsupported(): void
    {
        $plain = new class implements LegacyImporter
        {
            public function id(): string
            {
                return 'plain';
            }

            public function dependencies(): array
            {
                return [];
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
        };
        $declarations = new ImporterDeclarations;

        $built = $declarations->build([$plain], (new LegacyModuleCatalog)->knownModules());

        self::assertSame([], $built);
        self::assertSame(['plain' => 'declares no legacy coverage'], $declarations->skipped);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function incompleteDeclarations(): array
    {
        return [
            'unknown legacy module' => [['legacy_module' => 'Custom_Thing'], 'legacy module is not in the catalog'],
            'empty legacy module' => [['legacy_module' => ''], 'legacy module is not in the catalog'],
            'table owned by another module' => [['legacy_module' => 'CRM_Contacts', 'source_tables' => ['user_login'], 'required_columns' => ['user_login' => ['id']]], 'table user_login is not owned by CRM_Contacts'],
            'no required columns' => [['required_columns' => ['user_login' => []]], 'no required columns declared for user_login'],
            'columns for an unclaimed table' => [['ignored_columns' => ['company_data_1' => ['id']]], 'columns declared for a table the importer does not claim'],
            'column mapped and ignored' => [['ignored_columns' => ['user_login' => ['id']]], 'a column is both mapped and ignored'],
            'bad certification' => [['certified_for' => [['version' => '1.9.1']]], 'invalid certification entry'],
        ];
    }

    #[DataProvider('incompleteDeclarations')]
    public function test_an_incomplete_declaration_stays_unsupported(array $override, string $reason): void
    {
        $importer = new DeclarationStubImporter($override);
        $declarations = new ImporterDeclarations;

        self::assertSame([], $declarations->build([$importer], (new LegacyModuleCatalog)->knownModules()));
        self::assertSame($reason, $declarations->skipped['users']);
    }

    public function test_a_merged_module_is_certified_only_for_releases_every_importer_has(): void
    {
        $entry = ['version' => '1.9.1', 'fingerprint' => str_repeat('c', 64), 'module_version' => 1];
        $make = static fn (string $id, string $table, array $certified): CertifiableImporter => new DeclarationStubImporter([
            'id' => $id, 'legacy_module' => 'CRM_Contacts', 'source_tables' => [$table],
            'required_columns' => [$table => ['id']], 'certified_for' => $certified,
        ]);
        $known = (new LegacyModuleCatalog)->knownModules();

        $both = (new ImporterDeclarations)->build([$make('a', 'company_data_1', [$entry]), $make('b', 'contact_data_1', [$entry])], $known);
        $one = (new ImporterDeclarations)->build([$make('a', 'company_data_1', [$entry]), $make('b', 'contact_data_1', [])], $known);

        self::assertSame([$entry], $both[0]['certified_for']);
        self::assertSame([], $one[0]['certified_for']);
    }

    public function test_the_container_builds_an_installation_with_the_catalog_and_approval(): void
    {
        // Resolving it must work (it reads nothing until assess() is called).
        self::assertInstanceOf(LegacyInstallation::class, app(LegacyInstallation::class));
    }
}
