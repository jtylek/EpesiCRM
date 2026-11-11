<?php

namespace Tests\Feature;

use App\Services\LegacyImport\Importers\CompaniesImporter;
use App\Services\LegacyImport\Importers\ContactsImporter;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The extra columns of a legacy company table (fields added by other modules) become custom fields of
 * Company and their data is migrated: plain values with the row, the Account Manager link once the
 * contacts exist. Legacy is a second SQLite connection standing in for MySQL, so this proves the logic,
 * not MySQL DDL.
 */
class LegacyImportCustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy');
        $legacy = Schema::connection('legacy');

        $legacy->create('company_data_1', function (Blueprint $t) {
            $t->integer('id');
            $t->dateTime('created_on');
            $t->integer('created_by')->nullable();
            $t->integer('active')->default(1);
            $t->text('f_company_name')->nullable();
            $t->text('f_permission')->nullable();
            $t->integer('f_account_manager')->nullable();
            $t->float('f_default_transactions_markup')->nullable();
            $t->string('f_invoice_email')->nullable();
            $t->text('f_undeclared_note')->nullable();
            $t->text('f_scan')->nullable();
        });
        $legacy->create('company_field', function (Blueprint $t) {
            $t->string('field');
            $t->string('caption')->nullable();
            $t->string('type');
            $t->text('param')->nullable();
            $t->integer('visible')->default(0);
            $t->integer('required')->default(0);
            $t->integer('filter')->default(0);
            $t->integer('export')->default(1);
            $t->integer('active')->default(1);
            $t->integer('position');
            $t->text('help')->nullable();
        });
        $legacy->create('company_callback', function (Blueprint $t) {
            $t->string('field');
            $t->string('callback');
            $t->integer('freezed')->default(0);
        });
        $legacy->create('contact_data_1', function (Blueprint $t) {
            $t->integer('id');
            $t->dateTime('created_on');
            $t->integer('created_by')->nullable();
            $t->integer('active')->default(1);
            $t->text('f_last_name')->nullable();
            $t->text('f_first_name')->nullable();
        });

        $l = DB::connection('legacy');
        $field = fn (string $name, string $type, string $param, int $position) => ['field' => $name, 'type' => $type, 'param' => $param, 'position' => $position];
        $l->table('company_field')->insert([
            $field('Default Transactions Markup', 'float', '', 18),
            $field('Account Manager', 'select', 'contact::Last Name|First Name;CRM_ContactsCommon::contact_format_no_company;x', 19),
            $field('Invoice Email', 'text', '128', 20),
            $field('Scan', 'file', '', 21),
        ]);
        $l->table('company_callback')->insert(['field' => 'Invoice Email', 'callback' => 'CRM_ContactsCommon::display_email']);
        $l->table('contact_data_1')->insert(['id' => 7, 'created_on' => '2020-01-01 10:00:00', 'f_last_name' => 'Manager', 'f_first_name' => 'Ann']);
        $l->table('company_data_1')->insert([
            ['id' => 1, 'created_on' => '2020-01-01 10:00:00', 'f_company_name' => 'Acme', 'f_permission' => '0', 'f_account_manager' => 7, 'f_default_transactions_markup' => 12.5, 'f_invoice_email' => 'billing@acme.test', 'f_undeclared_note' => 'kept', 'f_scan' => 'x'],
            ['id' => 2, 'created_on' => '2020-01-01 10:00:00', 'f_company_name' => 'Nobody', 'f_permission' => '0', 'f_account_manager' => 99, 'f_default_transactions_markup' => null, 'f_invoice_email' => null, 'f_undeclared_note' => null, 'f_scan' => null],
        ]);
    }

    private function column(string $name): string
    {
        return (string) CustomField::query()->where('model_type', 'company')->where('name', $name)->value('column');
    }

    public function test_extra_columns_become_custom_fields_with_their_data_and_the_link_is_filled_after_contacts(): void
    {
        $this->artisan('import:legacy', ['tab' => 'companies', '--no-history' => true])->assertSuccessful();
        $this->artisan('import:legacy', ['tab' => 'contacts', '--no-history' => true])->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            ['account_manager', 'default_transactions_markup', 'invoice_email', 'undeclared_note', 'scan'],
            CustomField::query()->where('model_type', 'company')->pluck('name')->all(),
        );
        $this->assertSame('relation', CustomField::query()->where('name', 'account_manager')->value('type')->value);
        $this->assertSame('email', CustomField::query()->where('name', 'invoice_email')->value('type')->value);

        $acme = Company::query()->where('legacy_id', 1)->sole();
        $manager = Contact::query()->where('legacy_id', 7)->sole();
        $this->assertSame($manager->id, (int) $acme->getAttribute($this->column('account_manager')));
        $this->assertSame('billing@acme.test', $acme->getAttribute($this->column('invoice_email')));
        $this->assertEquals(12.5, $acme->getAttribute($this->column('default_transactions_markup')));
        $this->assertSame('kept', $acme->getAttribute($this->column('undeclared_note')));

        $nobody = Company::query()->where('legacy_id', 2)->sole();
        $this->assertNull($nobody->getAttribute($this->column('account_manager')), 'a manager that was not imported is left empty');
    }

    public function test_running_it_again_reuses_the_definitions(): void
    {
        foreach ([1, 2] as $_) {
            $this->artisan('import:legacy', ['tab' => 'companies', '--no-history' => true, '--keep-existing' => true])->assertSuccessful();
        }

        $this->assertSame(5, CustomField::query()->where('model_type', 'company')->count());
        $this->assertSame(2, Company::query()->whereNotNull('legacy_id')->count());
    }

    public function test_the_column_guard_names_the_column_whose_data_was_not_copied(): void
    {
        $this->artisan('import:legacy', ['tab' => 'companies', '--no-history' => true])->assertSuccessful();

        $counted = (new CompaniesImporter)->countedTables();
        $this->assertSame(['f_scan'], $counted[0]['unmigrated_columns'], 'a file field is added but its files are not copied yet');
        $this->assertSame([], (new ContactsImporter)->countedTables()[0]['unmigrated_columns']);
    }

    public function test_before_the_importer_ran_every_extra_column_is_unmigrated(): void
    {
        $this->assertEqualsCanonicalizing(
            ['f_account_manager', 'f_default_transactions_markup', 'f_invoice_email', 'f_undeclared_note', 'f_scan'],
            (new CompaniesImporter)->unmigratedColumns(),
        );
    }

    public function test_history_replays_the_custom_field_values(): void
    {
        $l = DB::connection('legacy');
        Schema::connection('legacy')->create('company_edit_history', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('company_id');
            $t->dateTime('edited_on');
            $t->integer('edited_by')->nullable();
        });
        Schema::connection('legacy')->create('company_edit_history_data', function (Blueprint $t) {
            $t->integer('edit_id');
            $t->string('field');
            $t->text('old_value')->nullable();
        });
        $l->table('company_edit_history')->insert(['id' => 1, 'company_id' => 1, 'edited_on' => '2021-01-01 10:00:00']);
        $l->table('company_edit_history_data')->insert(['edit_id' => 1, 'field' => 'invoice_email', 'old_value' => 'old@acme.test']);

        $this->artisan('import:legacy', ['tab' => 'companies'])->assertSuccessful();

        $company = Company::query()->where('legacy_id', 1)->sole();
        $update = $company->activities()->where('event', 'updated')->sole();
        $this->assertSame('old@acme.test', $update->properties['old'][$this->column('invoice_email')]);
        $this->assertSame('billing@acme.test', $update->properties['attributes'][$this->column('invoice_email')]);
    }
}
