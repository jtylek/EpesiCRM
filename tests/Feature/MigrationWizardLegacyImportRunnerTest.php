<?php

use Epesi\Modules\CRM\Companies\LegacyImport\CompaniesImporter;
use Epesi\Modules\CRM\Contacts\LegacyImport\ContactsImporter;
use Epesi\Modules\MigrationWizard\Import\Importers\UsersImporter;
use Epesi\Modules\MigrationWizard\Import\LegacyImporterRegistry;
use Epesi\Modules\MigrationWizard\Import\LegacyImportRunner;
use Tests\TestCase;

final class MigrationWizardLegacyImportRunnerTest extends TestCase
{
    public function test_builds_a_dependency_ordered_read_only_plan_from_the_m9_fixture(): void
    {
        $registeredIds = array_map(
            static fn ($importer): string => $importer->id(),
            app(LegacyImporterRegistry::class)->all(),
        );
        self::assertContains('users', $registeredIds);
        self::assertContains('companies', $registeredIds);
        self::assertContains('contacts', $registeredIds);

        $source = new PDO('sqlite::memory:');
        $source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $fixture = dirname(__DIR__).'/Fixtures/migration-wizard-mariadb.sql';
        $source->exec(file_get_contents($fixture));
        $source->exec('CREATE TABLE company_data_1 (id INTEGER PRIMARY KEY, f_company_name TEXT, f_custom TEXT)');
        $source->exec('CREATE TABLE contact_data_1 (id INTEGER PRIMARY KEY, f_first_name TEXT, f_last_name TEXT)');
        $source->exec("INSERT INTO company_data_1 VALUES (1, 'Fixture Company', 'kept')");
        $source->exec("INSERT INTO contact_data_1 VALUES (1, 'Fixture', 'Contact')");
        $changesBefore = (int) $source->query('SELECT total_changes()')->fetchColumn();

        $registry = new LegacyImporterRegistry;
        $registry->register(new ContactsImporter);
        $registry->register(new CompaniesImporter);
        $registry->register(new UsersImporter);

        $plan = (new LegacyImportRunner($registry))->dryRun(
            $source,
            [
                'user_login' => ['id', 'login'],
                'company_data_1' => ['id', 'f_company_name', 'f_custom'],
                'contact_data_1' => ['id', 'f_first_name', 'f_last_name'],
            ],
            [
                'users' => ['legacy_id', 'name'],
                'companies' => ['legacy_id', 'company_name'],
                'contacts' => ['legacy_id', 'first_name', 'last_name'],
            ],
        );

        self::assertSame('read_only_dry_run', $plan['mode']);
        self::assertFalse($plan['execution_allowed']);
        self::assertSame(['users', 'companies', 'contacts'], array_column($plan['importers'], 'id'));
        self::assertSame(['contact_access_roles'], $plan['importers'][0]['deferred_mappings']);
        self::assertSame(['App\Models\User'], $plan['importers'][0]['destination_models']);
        self::assertSame([], $plan['importers'][0]['source_file_areas']);
        self::assertSame(['legacy_user_ids_unique', 'user_count_reconciles'],
            $plan['importers'][0]['verification']['rules']);
        self::assertSame('not_run', $plan['importers'][0]['verification']['status']);
        self::assertSame(['f_custom'], $plan['importers'][1]['mapping']['unmapped_source_columns']);
        self::assertSame(1, $plan['importers'][0]['preview']['sampled_rows']);
        self::assertSame($changesBefore, (int) $source->query('SELECT total_changes()')->fetchColumn());
        self::assertStringNotContainsString('not-a-real-password', json_encode($plan));
    }

    public function test_registry_rejects_duplicate_importer_ids(): void
    {
        $registry = new LegacyImporterRegistry;
        $registry->register(new UsersImporter);

        $this->expectException(RuntimeException::class);
        $registry->register(new UsersImporter);
    }

    public function test_dry_run_fails_closed_when_an_importer_source_table_is_missing(): void
    {
        $registry = new LegacyImporterRegistry;
        $registry->register(new UsersImporter);

        $this->expectException(RuntimeException::class);
        (new LegacyImportRunner($registry))->dryRun(
            new PDO('sqlite::memory:'),
            [],
            ['users' => ['legacy_id', 'name']],
        );
    }
}
