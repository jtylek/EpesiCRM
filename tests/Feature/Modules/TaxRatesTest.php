<?php

namespace Tests\Feature\Modules;

use App\Services\LegacyImport\ImporterRegistry;
use Epesi\Modules\TaxRates\Filament\Resources\TaxRates\Pages\CreateTaxRate;
use Epesi\Modules\TaxRates\Filament\Resources\TaxRates\Pages\EditTaxRate;
use Epesi\Modules\TaxRates\Filament\Resources\TaxRates\Pages\ListTaxRates;
use Epesi\Modules\TaxRates\Filament\Resources\TaxRates\Pages\ViewTaxRate;
use Epesi\Modules\TaxRates\Filament\Resources\TaxRates\TaxRateResource;
use Epesi\Modules\TaxRates\LegacyImport\TaxRatesImporter;
use Epesi\Modules\TaxRates\Models\TaxRate;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The core Tax Rates module, and its migration taking the table over from the
 * Accounting module on an installation that has it there (bim.epe.si).
 */
class TaxRatesTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    private const MIGRATION = '2026_10_06_115000_create_tax_rates_table';

    private function migrations(): string
    {
        return base_path('modules/Epesi/TaxRates/database/migrations');
    }

    public function test_install_seeds_the_common_codes(): void
    {
        $this->assertSame(
            ['VAT23', 'VAT8', 'VAT5', 'ZERO', 'EXEMPT', 'REVERSE'],
            TaxRate::query()->orderBy('id')->pluck('code')->all(),
        );
        $this->assertSame('23.0000', TaxRate::query()->where('code', 'VAT23')->value('rate'));
        $this->assertSame('reduced', TaxRate::query()->where('code', 'VAT8')->value('tax_type'));
        $this->assertArrayHasKey('reduced', TaxRate::TYPES, 'every seeded type can be chosen in the form');
    }

    public function test_its_morph_alias_and_history_name_are_tax_rate(): void
    {
        $this->assertSame(TaxRate::class, Relation::getMorphedModel('tax_rate'));

        $rate = TaxRate::query()->create(['name' => 'VAT 21%', 'code' => 'VAT21', 'rate' => 21, 'tax_type' => 'standard']);

        $this->assertSame('tax_rate', $rate->getMorphClass());
        $this->assertTrue(DB::table('activity_log')->where(['subject_type' => 'tax_rate', 'subject_id' => $rate->id, 'log_name' => 'tax_rate'])->exists());
    }

    public function test_the_legacy_import_tab_runs_before_the_core_tabs(): void
    {
        $this->assertSame(TaxRatesImporter::class, app(ImporterRegistry::class)->before()['invoice_tax_rates'] ?? null);
    }

    public function test_the_menu_entry_is_in_administration_and_the_main_panel_resolves_titles(): void
    {
        $this->assertSame(TaxRateResource::class, Filament::getPanel('administration')->getModelResource(TaxRate::class));
        $this->assertSame(TaxRateResource::class, Filament::getPanel('main')->getModelResource(TaxRate::class));

        Filament::setCurrentPanel('administration');
        $this->assertTrue(TaxRateResource::shouldRegisterNavigation());

        Filament::setCurrentPanel('main');
        $this->assertFalse(TaxRateResource::shouldRegisterNavigation());
    }

    public function test_the_administration_pages_work_for_a_super_admin(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        // A session's first list opens on "My records" (RecordBrowser's
        // default for every list); the seeded rates have no creator.
        Livewire::test(ListTaxRates::class)
            ->assertOk()
            ->call('setMyRecordsMode', 'all')
            ->assertCanSeeTableRecords(TaxRate::query()->get());

        Livewire::test(CreateTaxRate::class)
            ->fillForm(['name' => 'VAT 21%', 'code' => 'VAT21', 'rate' => '21', 'tax_type' => 'standard', 'country_code' => 'CZ', 'active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $rate = TaxRate::query()->where('code', 'VAT21')->firstOrFail();
        $this->assertSame('21.0000', $rate->rate);
        $this->assertSame('CZ', $rate->country_code);

        Livewire::test(ViewTaxRate::class, ['record' => $rate->getKey()])->assertOk()->assertSee('VAT 21%');

        Livewire::test(EditTaxRate::class, ['record' => $rate->getKey()])
            ->fillForm(['rate' => '12'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('12.0000', $rate->refresh()->rate);
    }

    /** @return array<string, array{string, string}> */
    public static function accountingNames(): array
    {
        return [
            'after Accounting\'s rename to premium/accounting' => ['premium_accounting_tax_rates', 'premium_accounting_tax_rate'],
            'before it (epesi/accounting)' => ['epesi_accounting_tax_rates', 'accounting_tax_rate'],
        ];
    }

    #[DataProvider('accountingNames')]
    public function test_the_table_is_taken_over_from_accounting_with_its_ids(string $oldTable, string $oldAlias): void
    {
        $custom = TaxRate::query()->create(['name' => 'VAT 7%', 'code' => 'VAT7', 'rate' => 7, 'tax_type' => 'reduced']);
        $ids = DB::table('tax_rates')->orderBy('id')->pluck('code', 'id')->all();

        // As the Accounting module left it, before this module existed.
        Schema::rename('tax_rates', $oldTable);
        DB::table('migrations')->where('migration', self::MIGRATION)->delete();
        DB::table('activity_log')->where('subject_type', 'tax_rate')->update(['subject_type' => $oldAlias, 'log_name' => $oldAlias]);
        DB::table('activity_log')->insert(['log_name' => 'default', 'description' => 'created', 'subject_type' => 'company', 'subject_id' => $custom->id, 'event' => 'created']);
        DB::table('custom_fields')->insert(['model_type' => $oldAlias, 'column' => 'cf_98', 'name' => 'gtu', 'label' => 'GTU', 'type' => 'text']);
        DB::table('module_footprints')->insert(['module_id' => 'premium/accounting', 'kind' => 'table', 'table_name' => $oldTable, 'migration' => '2026_10_06_120000_create_premium_accounting_tables', 'created_at' => now()]);

        $this->artisan('migrate', ['--path' => $this->migrations(), '--realpath' => true])->assertSuccessful();

        $this->assertFalse(Schema::hasTable($oldTable));
        $this->assertSame($ids, DB::table('tax_rates')->orderBy('id')->pluck('code', 'id')->all(), 'same rows and ids, nothing seeded on top');
        $this->assertTrue(DB::table('migrations')->where('migration', self::MIGRATION)->exists());

        $this->assertFalse(DB::table('activity_log')->where('subject_type', $oldAlias)->orWhere('log_name', $oldAlias)->exists());
        $this->assertSame('tax_rate', DB::table('activity_log')->where('subject_id', $custom->id)->where('log_name', 'tax_rate')->value('subject_type'));
        $this->assertTrue(DB::table('activity_log')->where(['subject_type' => 'company', 'log_name' => 'default'])->exists(), 'other rows untouched');
        $this->assertSame('tax_rate', DB::table('custom_fields')->where('name', 'gtu')->value('model_type'));
        $this->assertFalse(DB::table('module_footprints')->where('table_name', $oldTable)->exists(), 'uninstalling Accounting leaves the table alone');

        $this->assertSame('VAT 7%', TaxRate::query()->find($custom->id)?->name);
    }

    public function test_on_an_installation_that_has_the_table_it_does_nothing_and_can_run_again(): void
    {
        DB::table('tax_rates')->where('code', 'VAT5')->delete();
        $rows = DB::table('tax_rates')->orderBy('id')->get()->all();

        $migration = require $this->migrations().'/'.self::MIGRATION.'.php';
        $migration->up();

        $this->assertEquals($rows, DB::table('tax_rates')->orderBy('id')->get()->all(), 'a deleted seed code stays deleted');
    }
}
