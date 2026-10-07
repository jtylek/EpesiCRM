<?php

namespace Tests\Feature\Modules;

use App\Models\Module;
use App\Services\LegacyImport\LegacyMoney;
use App\Services\Setup\ModulePlan;
use App\Services\Setup\SystemUpdate;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\CreateCompany;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\EditCompany;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ListCompanies;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ViewCompany;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\Currencies\Models\Currency;
use Epesi\Modules\Currencies\Services\CurrencyRepository;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldSchema;
use Epesi\Modules\RecordBrowser\Filament\RelationManagers\HistoryRelationManager;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Facades\Filament;
use Filament\Schemas\Components\FusedGroup;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The RecordBrowser `currency` field type: an amount and its ISO code in two
 * columns, entered side by side, shown as money, filtered by range and
 * currency, and one line in History.
 */
class CurrencyFieldTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    private function budgetField(): CustomField
    {
        return CustomField::create([
            'model_type' => 'company',
            'name' => 'budget',
            'label' => 'Budget',
            'type' => FieldType::Currency,
            'params' => ['decimals' => 2],
            'show_in_table' => true,
            'filterable' => true,
        ]);
    }

    private function money(string $amount, string $code): string
    {
        return app(CurrencyRepository::class)->format($amount, $code);
    }

    public function test_the_type_is_two_columns_with_a_decimal_cast(): void
    {
        $field = Field::currency('total_budget');

        $this->assertSame(FieldType::Currency, $field->type);
        $this->assertSame('total_budget_currency', $field->currencyColumn());
        $this->assertSame('decimal:2', $field->cast());
        $this->assertSame('decimal:0', Field::currency('yen', 0)->cast());
        $this->assertInstanceOf(FusedGroup::class, $field->toFormComponent());
        $this->assertFalse(FieldType::Currency->fitsCollectionItem());
        $this->assertTrue(FieldType::Currency->isAdministratorDefinable());
    }

    public function test_a_custom_currency_field_adds_and_drops_both_columns(): void
    {
        $table = (new Company)->getTable();
        $field = $this->budgetField();

        $this->assertTrue(Schema::hasColumn($table, $field->column));
        $this->assertTrue(Schema::hasColumn($table, $field->column.'_currency'));
        $this->assertSame([$field->column, $field->column.'_currency'], CustomFieldRegistry::columnsFor(Company::class));

        app(CustomFieldSchema::class)->drop($field);

        $this->assertFalse(Schema::hasColumn($table, $field->column));
        $this->assertFalse(Schema::hasColumn($table, $field->column.'_currency'));
    }

    public function test_a_decimal_field_can_become_a_currency_field(): void
    {
        $field = CustomField::create(['model_type' => 'company', 'name' => 'value', 'label' => 'Value', 'type' => FieldType::Decimal]);
        $this->assertFalse(Schema::hasColumn((new Company)->getTable(), $field->column.'_currency'));

        $field->update(['type' => FieldType::Currency]);

        $this->assertTrue(Schema::hasColumn((new Company)->getTable(), $field->column.'_currency'));
    }

    public function test_the_form_saves_both_columns_and_starts_at_the_users_currency(): void
    {
        $user = $this->userWithRole('manager');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('main'));
        $field = $this->budgetField();
        $column = $field->column;

        RegionalSetting::query()->create(['user_id' => $user->id, 'currency' => 'GBP']);

        Livewire::test(CreateCompany::class)
            ->assertSchemaStateSet(["{$column}_currency" => 'GBP'])
            ->fillForm(['company_name' => 'Acme', $column => '1234.5', "{$column}_currency" => 'EUR'])
            ->call('create')
            ->assertHasNoFormErrors();

        $company = Company::query()->where('company_name', 'Acme')->firstOrFail();
        $this->assertSame('1234.50', $company->{$column});
        $this->assertSame('EUR', $company->{"{$column}_currency"});

        // A currency is needed once there is an amount.
        Livewire::test(EditCompany::class, ['record' => $company->getKey()])
            ->fillForm([$column => '10', "{$column}_currency" => null])
            ->call('save')
            ->assertHasFormErrors(["{$column}_currency" => 'required']);
    }

    public function test_the_default_currency_falls_back_from_user_to_system_to_home(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);
        $currencies = app(CurrencyRepository::class);

        $this->assertSame('USD', $currencies->defaultCode(), 'the home currency');

        RegionalSetting::query()->create(['user_id' => null, 'currency' => 'PLN']);
        $this->assertSame('PLN', $currencies->defaultCode(), 'the system default');

        RegionalSetting::query()->create(['user_id' => $user->id, 'currency' => 'EUR']);
        $this->assertSame('EUR', $currencies->defaultCode(), 'the user\'s own');

        Currency::query()->where('code', 'EUR')->update(['active' => false]);
        $this->assertSame('USD', $currencies->defaultCode(), 'a deactivated currency is skipped');
    }

    public function test_view_list_and_filter_show_each_rows_currency(): void
    {
        $this->actingAs($this->userWithRole('manager'));
        Filament::setCurrentPanel(Filament::getPanel('main'));
        $column = $this->budgetField()->column;

        $euro = Company::create(['company_name' => 'Euro Ltd', $column => '1500', "{$column}_currency" => 'EUR']);
        $zloty = Company::create(['company_name' => 'Zloty SA', $column => '1500', "{$column}_currency" => 'PLN']);
        $small = Company::create(['company_name' => 'Small Co', $column => '20', "{$column}_currency" => 'EUR']);

        Livewire::test(ViewCompany::class, ['record' => $euro->getKey()])
            ->assertSee($this->money('1500', 'EUR'));

        Livewire::test(ListCompanies::class)
            ->assertSee($this->money('1500', 'EUR'))
            ->assertSee($this->money('1500', 'PLN'))
            ->filterTable($column, ['from' => '100', 'currency' => 'EUR'])
            ->assertCanSeeTableRecords([$euro])
            ->assertCanNotSeeTableRecords([$zloty, $small]);
    }

    public function test_history_shows_one_line_for_amount_and_currency(): void
    {
        $this->actingAs($this->userWithRole('manager'));
        Filament::setCurrentPanel(Filament::getPanel('main'));
        $column = $this->budgetField()->column;

        $company = Company::create(['company_name' => 'Acme', $column => '10', "{$column}_currency" => 'PLN']);
        $company->update([$column => '12', "{$column}_currency" => 'EUR']);
        $company->update([$column => '15']);

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $company, 'pageClass' => ViewCompany::class])
            ->assertSeeHtml('Budget: <span class="epesi-history-old">'.e($this->money('10', 'PLN')).'</span> → <span class="epesi-history-new">'.e($this->money('12', 'EUR')).'</span>')
            ->assertSeeHtml('Budget: <span class="epesi-history-old">'.e($this->money('12', 'EUR')).'</span> → <span class="epesi-history-new">'.e($this->money('15', 'EUR')).'</span>')
            ->assertDontSeeText('Budget Currency');
    }

    public function test_a_legacy_value_splits_into_amount_and_code(): void
    {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy');
        Schema::connection('legacy')->create('utils_currency', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->integer('default_currency');
        });
        DB::connection('legacy')->table('utils_currency')->insert([
            ['code' => 'usd', 'default_currency' => 0],
            ['code' => 'PLN', 'default_currency' => 1],
        ]);

        // Another test may have filled its cache from a different legacy database.
        (new \ReflectionProperty(LegacyMoney::class, 'codes'))->setValue(null, null);

        $this->assertSame(['60', 'USD'], LegacyMoney::parse('60__1'));
        $this->assertSame(['12.5', 'PLN'], LegacyMoney::parse('12.5'), 'no currency id: the legacy default');
        $this->assertSame([null, null], LegacyMoney::parse(''));
    }

    public function test_an_update_registers_a_core_module_the_install_does_not_have_yet(): void
    {
        config(['modules.from_manifests' => false]);

        foreach (app(ModulePlan::class)->for([]) as $manifest) {
            if ($manifest->id !== 'epesi/currencies') {
                Module::create(['enabled' => true, 'installed_at' => now()] + $manifest->toDatabaseRow());
            }
        }

        $update = app(SystemUpdate::class);

        $this->assertSame(['epesi/currencies'], array_keys($update->newCoreModules()));
        $this->assertSame(['epesi/currencies'], $update->pending()['New core modules']);
    }
}
