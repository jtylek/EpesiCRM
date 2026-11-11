<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LegacyImport\Importers\CompaniesImporter;
use App\Services\LegacyImport\LegacyImportSet;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\MigrationWizard\Import\InPlaceLegacyImport;
use Epesi\Modules\MigrationWizard\Orchestration\OperatorChoices;
use Epesi\Modules\MigrationWizard\Verification\MigrationVerifier;
use Epesi\Modules\MigrationWizard\Verification\VerificationInputBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The app's own import:legacy importers, run in place against legacy_<name>
 * tables: the `legacy` connection (a second in-memory SQLite database here,
 * standing in for the same MySQL database) carries the legacy_ prefix, so the
 * importers' unprefixed table names resolve to the renamed tables.
 */
final class MigrationWizardInPlaceImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->legacyConnection(InPlaceLegacyImport::PREFIX);

        $legacy = Schema::connection('legacy');
        $legacy->create('user_login', function (Blueprint $t) {
            $t->integer('id');
            $t->string('login');
            $t->integer('active')->default(1);
        });
        $legacy->create('user_password', function (Blueprint $t) {
            $t->integer('user_login_id');
            $t->string('mail')->nullable();
        });
        $legacy->create('company_data_1', function (Blueprint $t) {
            $t->integer('id');
            $t->dateTime('created_on');
            $t->integer('created_by')->nullable();
            $t->integer('active')->default(1);
            foreach (['company_name', 'short_name', 'phone', 'fax', 'email', 'web_address', 'memo', 'group', 'permission',
                'address_1', 'address_2', 'city', 'country', 'zone', 'postal_code', 'tax_id'] as $f) {
                $t->text("f_{$f}")->nullable();
            }
        });
        $legacy->create('company_edit_history', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('company_id');
            $t->dateTime('edited_on');
            $t->integer('edited_by')->nullable();
        });
        $legacy->create('company_edit_history_data', function (Blueprint $t) {
            $t->integer('edit_id');
            $t->string('field');
            $t->text('old_value')->nullable();
        });

        $l = DB::connection('legacy');
        $l->table('user_login')->insert([['id' => 1, 'login' => 'root', 'active' => 1], ['id' => 2, 'login' => 'sub', 'active' => 0]]);
        $l->table('user_password')->insert([['user_login_id' => 1, 'mail' => 'root@example.test'], ['user_login_id' => 2, 'mail' => 'sub@example.test']]);
        $l->table('company_data_1')->insert([
            ['id' => 3, 'created_on' => '2020-01-01 10:00:00', 'created_by' => 1, 'active' => 1, 'f_company_name' => 'Acme Ltd', 'f_permission' => '0'],
            ['id' => 4, 'created_on' => '2020-01-02 10:00:00', 'created_by' => 1, 'active' => 0, 'f_company_name' => 'Deleted Co', 'f_permission' => '0'],
        ]);
    }

    private function legacyConnection(string $prefix): void
    {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => $prefix]]);
        DB::purge('legacy');
    }

    private function engine(): InPlaceLegacyImport
    {
        return new InPlaceLegacyImport(app(LegacyImportSet::class));
    }

    /** @return list<string> every tab except these, so only the given ones run */
    private function allBut(array $tabs): array
    {
        return array_values(array_diff(array_keys($this->engine()->tabs()), $tabs));
    }

    public function test_the_legacy_connection_prefix_is_configurable_and_the_tables_resolve_through_it(): void
    {
        // The legacy tables exist as legacy_user_login / legacy_company_data_1 on the prefixed connection.
        $physical = DB::connection('legacy')->getSchemaBuilder()->getTables();
        $names = array_column($physical, 'name');
        self::assertContains('legacy_user_login', $names);
        self::assertContains('legacy_company_data_1', $names);
        self::assertSame(2, DB::connection('legacy')->table('user_login')->count());
        self::assertSame('legacy_', config('database.connections.legacy.prefix'));
    }

    public function test_the_importers_read_the_renamed_tables_and_record_each_one_as_it_finishes(): void
    {
        $recorded = [];
        $ran = $this->engine()->run($this->allBut(['users', 'companies']), function (string $tab, array $result) use (&$recorded): void {
            $recorded[$tab] = $result;
        });

        self::assertSame(['users', 'companies'], $ran);
        self::assertSame(['users', 'companies'], array_keys($recorded));
        self::assertSame(2, $recorded['users']['created']);
        self::assertSame(2, $recorded['companies']['created']);
        self::assertEqualsCanonicalizing([1, 2], User::whereNotNull('legacy_id')->pluck('legacy_id')->all());
        self::assertSame(['Acme Ltd', 'Deleted Co'], Company::withTrashed()->orderBy('legacy_id')->get()->pluck('company_name')->all());
        // Legacy's inactive record is imported as deleted, never dropped.
        self::assertTrue(Company::withTrashed()->where('legacy_id', 4)->sole()->trashed());
    }

    public function test_a_completed_importer_is_skipped_and_a_rerun_updates_instead_of_duplicating(): void
    {
        $this->engine()->run($this->allBut(['users', 'companies']), static function (): void {});

        $again = [];
        $ran = $this->engine()->run($this->allBut(['companies']), function (string $tab, array $result) use (&$again): void {
            $again[$tab] = $result;
        });

        self::assertSame(['companies'], $ran);
        self::assertSame(0, $again['companies']['created']);
        self::assertSame(2, $again['companies']['updated']);
        self::assertSame(2, Company::withTrashed()->count());
        self::assertSame(2, User::whereNotNull('legacy_id')->count());
    }

    public function test_a_target_with_hand_entered_rows_is_refused_before_anything_is_imported(): void
    {
        Company::create(['company_name' => 'Entered here']);

        try {
            $this->engine()->run($this->allBut(['users', 'companies']), static function (): void {});
            self::fail('The wizard never imports alongside rows without a legacy id.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('must be clean', $e->getMessage());
        }
        self::assertSame(0, User::whereNotNull('legacy_id')->count());
        self::assertSame(1, Company::withTrashed()->count());
    }

    public function test_an_unprefixed_legacy_connection_is_refused(): void
    {
        $this->legacyConnection('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('legacy_ table prefix');
        $this->engine()->run($this->allBut(['users']), static function (): void {});
    }

    public function test_a_legacy_connection_to_another_database_is_refused(): void
    {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => 'other-database', 'prefix' => 'legacy_']]);
        DB::purge('legacy');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('another database');
        $this->engine()->run($this->allBut(['users']), static function (): void {});
    }

    public function test_a_missing_renamed_user_table_is_refused(): void
    {
        $this->legacyConnection(InPlaceLegacyImport::PREFIX); // a fresh, empty database

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('legacy_user_login');
        $this->engine()->run($this->allBut(['users']), static function (): void {});
    }

    public function test_importers_that_bring_business_files_over_are_run_in_place_and_flagged_as_file_tabs(): void
    {
        $tabs = array_keys($this->engine()->tabs());

        self::assertContains('users', $tabs);
        self::assertContains('companies', $tabs);
        self::assertContains('contacts', $tabs);
        self::assertContains('attachments', $tabs);
        self::assertContains('mail', $tabs);
        self::assertSame([], InPlaceLegacyImport::NOT_RUN);
        self::assertSame(['mail', 'attachments'], InPlaceLegacyImport::FILE_TABS);
        foreach (InPlaceLegacyImport::FILE_TABS as $tab) {
            self::assertContains($tab, $tabs);
        }
    }

    public function test_an_importer_whose_legacy_module_is_not_approved_is_left_out_and_others_run(): void
    {
        $tabs = fn (array $approved): array => array_keys($this->engine()->tabs($approved));

        self::assertSame(array_keys($this->engine()->tabs()), $tabs(['Base_User', 'CRM_Contacts', 'CRM_Mail', 'Utils_Attachment', 'Utils_FileStorage']));
        $withoutContacts = $tabs(['Base_User']);
        self::assertContains('users', $withoutContacts);
        self::assertNotContains('companies', $withoutContacts);
        self::assertNotContains('contacts', $withoutContacts);
        // Importers with no module mapping (phone calls, tasks, ...) are not filtered.
        self::assertContains('tasks', $withoutContacts);
        self::assertSame([], array_values(array_intersect(['users', 'companies', 'contacts', 'mail', 'attachments'], $tabs([]))));

        self::assertSame(['Base_User'], InPlaceLegacyImport::approvedModules(['modules' => [
            ['legacy_module' => 'Base_User', 'schema_approved' => true],
            ['legacy_module' => 'CRM_Contacts', 'schema_approved' => false],
            ['legacy_module' => 'Premium_X', 'schema_approved' => null],
        ]]));
        self::assertSame([], InPlaceLegacyImport::approvedModules([]));
    }

    public function test_the_verification_input_counts_source_and_destination_rows_for_completed_importers_only(): void
    {
        $this->engine()->run($this->allBut(['users', 'companies']), static function (): void {});
        $builder = new VerificationInputBuilder(DB::connection('legacy'), ['companies' => CompaniesImporter::class], static fn (string $class): object => app($class));
        $coverage = ['tables' => [], 'files' => [], 'unclassified_tables' => ['custom_invoice'], 'unclassified_file_areas' => ['data/Custom_Invoice']];

        $input = $builder->build($coverage, ['users', 'companies'], ['preserve' => [OperatorChoices::UNKNOWN, 'extra'], 'discard' => ['old_cache'], 'core_only' => true], ['checked' => 0, 'problems' => []]);

        self::assertSame([['label' => 'Users', 'source' => 2, 'destination' => 2], ['label' => 'Companies', 'source' => 2, 'destination' => 2]], $input['counts']);
        self::assertEqualsCanonicalizing([1, 2], $input['legacy_ids'][0]['source_ids']);
        self::assertEqualsCanonicalizing([3, 4], $input['legacy_ids'][1]['destination_ids']);
        self::assertEqualsCanonicalizing(['extra', 'custom_invoice', 'data/Custom_Invoice'], $input['preserved']);
        self::assertSame(['old_cache'], $input['approved_discards']);
        self::assertTrue($input['core_only']);
        self::assertSame(['users', 'companies'], $input['completed_importers']);
        // The counts are taken from the database now, per declared table, with the skip rule (none here) beside them.
        self::assertSame([
            ['label' => 'Users', 'importer' => 'users', 'table' => 'user_login', 'source' => 2, 'imported' => 2, 'skipped' => 0, 'skip_label' => null, 'missing_ids' => 0, 'unmigrated_columns' => []],
            ['label' => 'Companies', 'importer' => 'companies', 'table' => 'company_data_1', 'source' => 2, 'imported' => 2, 'skipped' => 0, 'skip_label' => null, 'missing_ids' => 0, 'unmigrated_columns' => []],
        ], $input['table_certification']);

        // An importer that did not complete contributes no count to claim.
        $partial = $builder->build($coverage, ['users'], [], null);
        self::assertSame(['Users'], array_column($partial['counts'], 'label'));
        self::assertNull($partial['ledger']);
    }

    public function test_a_missing_destination_row_keeps_that_table_and_blocks_nothing(): void
    {
        $this->engine()->run($this->allBut(['users', 'companies']), static function (): void {});
        Company::withTrashed()->where('legacy_id', 4)->forceDelete();
        $builder = new VerificationInputBuilder(DB::connection('legacy'), ['companies' => CompaniesImporter::class], static fn (string $class): object => app($class));

        $input = $builder->build(['tables' => [['table' => 'company_data_1', 'outcome' => 'unknown', 'estimated_rows' => 2], ['table' => 'user_login', 'outcome' => 'unknown', 'estimated_rows' => 2]], 'files' => []], ['users', 'companies'], [], ['checked' => 0, 'problems' => []]);
        $report = (new MigrationVerifier)->verify($input);

        $companies = collect($report['table_certification'])->firstWhere('table', 'company_data_1');
        self::assertSame(['kept', 2, 1, 1], [$companies['status'], $companies['source'], $companies['imported'], $companies['missing_ids']]);
        self::assertContains('company_data_1', $report['preserved_tables']);
        self::assertNotContains('company_data_1', $report['cleanup_eligible_tables']);
        self::assertContains('user_login', $report['cleanup_eligible_tables'], 'the users table is certified on its own counts');
        self::assertSame([], $report['blocking']);
        self::assertTrue($report['cleanup_allowed']);
    }

    public function test_a_counted_table_and_its_side_tables_are_never_removed_uncounted_as_nobodys(): void
    {
        $this->engine()->run($this->allBut(['users', 'companies']), static function (): void {});
        Company::withTrashed()->where('legacy_id', 4)->forceDelete();
        $builder = new VerificationInputBuilder(DB::connection('legacy'), ['companies' => CompaniesImporter::class], static fn (string $class): object => app($class));
        $row = static fn (string $table): array => ['table' => $table, 'outcome' => 'unknown', 'estimated_rows' => 2];

        $input = $builder->build(['tables' => [$row('company_data_1'), $row('company_edit_history'), $row('old_thing'), $row('premium_shop')], 'files' => []], ['users', 'companies'], ['discard_unknown' => true], ['checked' => 0, 'problems' => []]);

        self::assertSame(['old_thing'], $input['unowned']['tables']);
        $report = (new MigrationVerifier)->verify($input);
        self::assertEqualsCanonicalizing(['company_data_1', 'company_edit_history', 'premium_shop'], $report['preserved_tables']);
    }
}
