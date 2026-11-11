<?php

namespace Tests\Feature;

use App\Services\LegacyImport\Importers\CompaniesImporter;
use App\Services\LegacyImport\Importers\ContactsImporter;
use App\Services\LegacyImport\LegacyMultipleAddresses;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\MigrationWizard\Import\InPlaceLegacyImport;
use Epesi\Modules\MigrationWizard\Verification\MigrationVerifier;
use Epesi\Modules\MigrationWizard\Verification\VerificationInputBuilder;
use Epesi\Modules\RecordBrowser\Models\Address;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Legacy Premium_MultipleAddresses -> Shipping addresses of the owner and of the company the row names,
 * through the companies and contacts importers, and the table's release by the Migration Wizard.
 */
final class LegacyMultipleAddressesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy');
        $legacy = Schema::connection('legacy');

        foreach (['company_data_1' => ['company_name', 'address_1', 'city'], 'contact_data_1' => ['company_name', 'last_name', 'first_name', 'email']] as $table => $fields) {
            $legacy->create($table, function (Blueprint $t) use ($fields) {
                $t->integer('id');
                $t->dateTime('created_on');
                $t->integer('created_by')->nullable();
                $t->integer('active')->default(1);
                foreach ($fields as $f) {
                    $t->text("f_{$f}")->nullable();
                }
            });
        }

        $legacy->create('premium_multiple_addresses_data_1', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('active')->default(1);
            foreach (['record_type', 'record_id', 'nickname', 'last_name', 'first_name', 'company_name', 'address_1', 'address_2', 'city', 'country', 'zone', 'postal_code'] as $f) {
                $t->text("f_{$f}")->nullable();
            }
        });

        $l = DB::connection('legacy');
        $l->table('company_data_1')->insert([
            ['id' => 10, 'created_on' => '2020-01-01 10:00:00', 'active' => 1, 'f_company_name' => 'Acme Ltd', 'f_address_1' => 'HQ 1', 'f_city' => 'Warsaw'],
            ['id' => 11, 'created_on' => '2020-01-01 10:00:00', 'active' => 1, 'f_company_name' => 'Twin', 'f_address_1' => null, 'f_city' => null],
            ['id' => 12, 'created_on' => '2020-01-01 10:00:00', 'active' => 1, 'f_company_name' => 'TWIN', 'f_address_1' => null, 'f_city' => null],
            ['id' => 13, 'created_on' => '2020-01-01 10:00:00', 'active' => 0, 'f_company_name' => 'Gone Inc', 'f_address_1' => null, 'f_city' => null],
        ]);
        $l->table('contact_data_1')->insert([
            ['id' => 1, 'created_on' => '2020-01-01 10:00:00', 'f_last_name' => 'Buyer', 'f_first_name' => 'Ann'],
            ['id' => 2, 'created_on' => '2020-01-01 10:00:00', 'f_last_name' => 'Other', 'f_first_name' => 'Bob'],
        ]);

        $address = fn (int $id, array $values): array => array_merge([
            'id' => $id, 'active' => 1, 'f_record_type' => 'contact', 'f_record_id' => 1, 'f_nickname' => 'Home office',
            'f_last_name' => 'Buyer', 'f_first_name' => 'Ann', 'f_country' => 'PL', 'f_company_name' => null,
            'f_address_1' => null, 'f_address_2' => null, 'f_city' => null, 'f_zone' => null, 'f_postal_code' => null,
        ], $values);

        $l->table('premium_multiple_addresses_data_1')->insert([
            // Owner contact 1, company "Acme Ltd" (trimmed, any case) matches one active company.
            $address(1, ['f_company_name' => ' acme ltd ', 'f_address_1' => 'Dock 5', 'f_city' => 'Gdansk', 'f_postal_code' => '80-001', 'f_zone' => 'PM']),
            // Same address twice for the same owner: one item.
            $address(2, ['f_company_name' => 'Acme Ltd', 'f_address_1' => 'dock 5', 'f_city' => 'Gdansk', 'f_postal_code' => '80-001', 'f_zone' => 'PM']),
            // Ambiguous company name (two active companies), unknown one, and none.
            $address(3, ['f_company_name' => 'Twin', 'f_address_1' => 'Pier 1', 'f_city' => 'Sopot']),
            $address(4, ['f_company_name' => 'Nobody Ltd', 'f_address_1' => 'Pier 2', 'f_city' => 'Sopot']),
            $address(5, ['f_record_id' => 2, 'f_address_1' => 'Pier 3', 'f_city' => 'Sopot']),
            // The inactive company doesn't count as a match.
            $address(6, ['f_record_id' => 2, 'f_company_name' => 'Gone Inc', 'f_address_1' => 'Pier 4', 'f_city' => 'Sopot']),
            // Not imported: inactive, only a country, an owner that doesn't exist.
            $address(7, ['active' => 0, 'f_address_1' => 'Inactive 1', 'f_city' => 'Sopot']),
            $address(8, ['f_address_1' => null, 'f_city' => null]),
            $address(9, ['f_record_id' => 99, 'f_address_1' => 'Ghost 1', 'f_city' => 'Sopot']),
            // A company's own row.
            $address(10, ['f_record_type' => 'company', 'f_record_id' => 11, 'f_address_1' => 'Twin Yard', 'f_city' => 'Lodz']),
        ]);
    }

    /** @return array{0: Company, 1: Contact, 2: Contact} the Acme company and contacts 1 and 2 after a full import */
    private function import(): array
    {
        activity()->disableLogging();
        (new CompaniesImporter)->run(withHistory: false);
        $summary = (new ContactsImporter)->run(withHistory: false);
        activity()->enableLogging();

        $this->summary = $summary;

        return [
            Company::withTrashed()->where('legacy_id', 10)->sole(),
            Contact::withTrashed()->where('legacy_id', 1)->sole(),
            Contact::withTrashed()->where('legacy_id', 2)->sole(),
        ];
    }

    private $summary;

    /** @return list<string> */
    private function shipping($record): array
    {
        return $record->collection('addresses')->where('kind', 'shipping')->get()
            ->map(fn (Address $a): string => implode('|', [$a->address_1, $a->postal_code, $a->city, $a->zone, $a->country]))->all();
    }

    public function test_a_row_becomes_a_shipping_address_of_its_owner_and_of_the_company_it_names(): void
    {
        [$acme, $ann, $bob] = $this->import();

        $this->assertSame(
            ['Dock 5|80-001|Gdansk|PM|PL', 'Pier 1||Sopot||PL', 'Pier 2||Sopot||PL'],
            $this->shipping($ann),
            'the duplicate row is one item; the nickname and names are not kept',
        );
        $this->assertSame(['Pier 3||Sopot||PL', 'Pier 4||Sopot||PL'], $this->shipping($bob));

        // The company keeps its own business address first, then the copy of the row that names it.
        $this->assertSame(['business', 'shipping'], $acme->collection('addresses')->get()->pluck('kind')->all());
        $this->assertSame(['Dock 5|80-001|Gdansk|PM|PL'], $this->shipping($acme));

        // A row owned by a company goes to that company; "Twin" matched two companies, so no copy anywhere else.
        $this->assertSame(['Twin Yard||Lodz||PL'], $this->shipping(Company::withTrashed()->where('legacy_id', 11)->sole()));
        $this->assertSame([], $this->shipping(Company::withTrashed()->where('legacy_id', 12)->sole()));

        $this->assertSame(0, Address::query()->where('address_1', 'like', 'Inactive%')->orWhere('address_1', 'Ghost 1')->count());
    }

    public function test_unmatched_ambiguous_and_ownerless_rows_are_reported_never_guessed(): void
    {
        $this->import();

        $this->assertEqualsCanonicalizing([
            'multiple address #3: company "Twin" matches 2 active companies, not copied to a company',
            'multiple address #4: company "Nobody Ltd" matches no active company, not copied to a company',
            'multiple address #6: company "Gone Inc" matches no active company, not copied to a company',
            "multiple address #9: its owner contact#99 doesn't exist, skipped",
        ], $this->summary->warnings);
    }

    public function test_running_the_import_again_adds_nothing(): void
    {
        $this->import();
        $before = Address::query()->count();

        [$acme, $ann] = $this->import();

        $this->assertSame($before, Address::query()->count());
        $this->assertCount(3, $this->shipping($ann));
        $this->assertCount(1, $this->shipping($acme));
    }

    public function test_without_the_premium_table_nothing_changes(): void
    {
        Schema::connection('legacy')->drop('premium_multiple_addresses_data_1');

        [$acme, $ann] = $this->import();

        $this->assertSame([], $this->shipping($ann));
        $this->assertSame(['business'], $acme->collection('addresses')->get()->pluck('kind')->all());
        $this->assertSame([], $this->summary->warnings);
    }

    public function test_the_count_for_the_wizard_finds_every_address_and_a_missing_one(): void
    {
        [$acme] = $this->import();
        $count = fn (): array => (new LegacyMultipleAddresses)->verification();

        // 10 rows; imported: 1-6 and 10 (7 inactive, 8 no address, 9 no owner are skipped by design).
        $this->assertSame(['source' => 10, 'skipped' => 3, 'imported' => 7, 'missing' => 0], $count());

        $acme->collection('addresses')->where('kind', 'shipping')->delete();

        // Row 1 and 2 (the same address) both lack their copy on the company.
        $this->assertSame(['source' => 10, 'skipped' => 3, 'imported' => 5, 'missing' => 2], $count());
    }

    public function test_the_table_and_its_side_tables_are_released_only_when_every_address_is_there(): void
    {
        [$acme] = $this->import();
        $legacy = Schema::connection('legacy');
        $legacy->create('premium_multiple_addresses_field', function (Blueprint $t) {
            $t->integer('id');
        });
        $coverage = ['tables' => [
            ['table' => 'premium_multiple_addresses_data_1', 'outcome' => 'unknown', 'estimated_rows' => 10],
            ['table' => 'premium_multiple_addresses_field', 'outcome' => 'unknown', 'estimated_rows' => 1],
        ], 'files' => []];
        $build = fn (array $completed) => (new VerificationInputBuilder(DB::connection('legacy'), [], static fn (string $class): object => app($class)))->build($coverage, $completed, [], ['checked' => 0, 'problems' => []]);
        $verify = fn (array $completed): array => (new MigrationVerifier)->verify($build($completed));

        $report = $verify(['contacts']);
        $this->assertEqualsCanonicalizing(['premium_multiple_addresses_data_1', 'premium_multiple_addresses_field'], $report['cleanup_eligible_tables']);
        $this->assertSame([], $report['blocking']);

        // Contacts not completed: the unknown tables still block, nothing is released.
        $early = $verify([]);
        $this->assertSame([], $early['cleanup_eligible_tables']);
        $this->assertNotSame([], $early['blocking']);

        // An address that did not arrive keeps the whole group.
        $acme->collection('addresses')->where('kind', 'shipping')->delete();
        $kept = $verify(['contacts']);
        $this->assertSame([], $kept['cleanup_eligible_tables']);
        $this->assertEqualsCanonicalizing(['premium_multiple_addresses_data_1', 'premium_multiple_addresses_field'], $kept['preserved_tables']);

        // --preserve still wins over the release.
        $input = $build(['contacts']);
        $input['preserved'] = ['premium_multiple_addresses_data_1'];
        $this->assertNotContains('premium_multiple_addresses_data_1', (new MigrationVerifier)->verify($input)['cleanup_eligible_tables']);
    }

    public function test_the_scan_lists_premium_multiple_addresses_as_migrated_through_the_contacts_importer(): void
    {
        $this->assertContains('Premium_MultipleAddresses', array_keys(InPlaceLegacyImport::moduleImporters(['contacts' => 'x'])));
        $this->assertSame(['contacts'], InPlaceLegacyImport::moduleImporters(['contacts' => 'x'])['Premium_MultipleAddresses']);
        // The approval gate is untouched: it claims no table of this Premium module.
        $this->assertSame(['CRM_Contacts'], InPlaceLegacyImport::MODULES['contacts']);
    }
}
