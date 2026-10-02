<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\CompanyResource;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\CreateCompany;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ViewCompany;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages\ListCustomFields;
use Epesi\Modules\RecordBrowser\Models\Address;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\FieldOverrides;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\Watchdog\Watchdog;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class FieldOverridesTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');
    }

    public function test_catalogue_lists_shipped_and_custom_fields_and_filters_them(): void
    {
        CustomField::create(['model_type' => 'company', 'name' => 'gate_code', 'label' => 'Gate code', 'type' => FieldType::Text]);
        $page = Livewire::test(ListCustomFields::class)->filterTable('model_type', 'company')->assertSuccessful();
        $this->assertArrayHasKey('module:company:company_name', $page->instance()->getTableRecords()->getCollection()->all());
        $page->filterTable('origin', 'Custom');
        $rows = $page->instance()->getTableRecords();
        $this->assertCount(1, $rows);
        $this->assertSame('Gate code', $rows->first()['label']);
    }

    public function test_editor_saves_only_explicit_overrides_and_reset_restores_defaults(): void
    {
        $row = 'module:company:short_name';
        Livewire::test(ListCustomFields::class)
            ->filterTable('model_type', 'company')
            ->callAction(TestAction::make('edit')->table($row), data: [
                'inherit' => ['label' => false],
                'properties' => ['label' => 'Trading name'],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(['label' => 'Trading name'], app(FieldOverrides::class)->properties('company', 'short_name'));
        $this->assertSame('Trading name', $this->companyField('short_name')->getLabel());
        Livewire::test(ListCustomFields::class)
            ->filterTable('model_type', 'company')
            ->callAction(TestAction::make('reset')->table($row))
            ->assertHasNoActionErrors();
        $this->assertSame('Short Name', $this->companyField('short_name')->getLabel());
        $this->assertDatabaseCount(FieldOverrides::TABLE, 0);
    }

    public function test_settings_reach_forms_views_columns_filters_and_watchdog(): void
    {
        app(FieldOverrides::class)->save('company', 'short_name', [
            'label' => 'Trading name', 'required' => true, 'filterable' => true,
            'section' => 'Identity', 'help' => 'Public trading name',
        ]);
        Filament::setCurrentPanel('main');
        Livewire::test(CreateCompany::class)->assertSee('Trading name')->assertSee('Public trading name')
            ->fillForm(['company_name' => 'Acme'])->call('create')->assertHasFormErrors(['short_name' => 'required']);
        $company = Company::create(['company_name' => 'Acme', 'short_name' => 'AC']);
        Livewire::test(ViewCompany::class, ['record' => $company->id])->assertSee('Trading name')->assertSee('AC');
        $field = $this->companyField('short_name');
        $this->assertSame('Trading name', $field->toTableColumn()->getLabel());
        $this->assertNotNull($field->toTableFilter());
        $this->assertSame('Trading name', Watchdog::fieldLabel($company, 'short_name'));
    }

    public function test_hidden_fields_keep_stored_values_and_history(): void
    {
        $company = Company::create(['company_name' => 'Acme', 'short_name' => 'AC']);
        app(FieldOverrides::class)->save('company', 'short_name', ['show_in_form' => false, 'show_in_view' => false]);
        Filament::setCurrentPanel('main');
        Livewire::test(ViewCompany::class, ['record' => $company->id])->assertDontSee('Short name');
        $this->assertFalse($this->companyField('short_name')->isInForm());
        $this->assertSame('AC', $company->fresh()->short_name);
        $this->assertTrue($company->activities()->exists());
    }

    public function test_protected_and_structural_properties_are_rejected(): void
    {
        foreach ([['required' => false], ['show_in_form' => false], ['type' => 'integer'], ['name' => 'renamed']] as $properties) {
            try {
                app(FieldOverrides::class)->save('company', 'company_name', $properties);
                $this->fail('Protected property was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('properties', $exception->errors());
            }
        }
        $this->assertDatabaseCount(FieldOverrides::TABLE, 0);
    }

    public function test_required_field_cannot_be_hidden_by_combining_settings(): void
    {
        $this->expectException(ValidationException::class);
        app(FieldOverrides::class)->save('company', 'short_name', ['required' => true, 'show_in_form' => false]);
    }

    public function test_module_updates_keep_unoverridden_defaults_and_can_lock_existing_overrides(): void
    {
        app(FieldOverrides::class)->save('company', 'short_name', ['label' => 'Trading name', 'position' => 100]);
        $new = Field::text('short_name')->help('New module help');
        $resolved = app(FieldOverrides::class)->resolve(Company::class, [$new, Field::text('memo')]);
        $this->assertSame('short_name', $resolved[1]->name);
        $this->assertSame('New module help', $resolved[1]->administratorDefaults()['help']);
        $this->assertSame('Short Name', $new->getLabel());
        $locked = app(FieldOverrides::class)->resolve(Company::class, [$new->administratorEditable([])]);
        $this->assertSame('Short Name', $locked[0]->getLabel());
    }

    public function test_collection_item_fields_use_overrides(): void
    {
        app(FieldOverrides::class)->save('address', 'city', ['label' => 'Town']);
        $field = collect(Address::resolvedFields())->firstWhere('name', 'city');
        $this->assertSame('Town', $field->getLabel());
        $this->assertSame(['label' => 'Town'], app(FieldOverrides::class)->properties('address', 'city'));
    }

    public function test_property_inheritance_can_be_restored_without_resetting_other_settings(): void
    {
        app(FieldOverrides::class)->save('company', 'short_name', ['label' => 'Trading name', 'help' => 'A trading name']);
        Livewire::test(ListCustomFields::class)
            ->filterTable('model_type', 'company')
            ->callAction(TestAction::make('edit')->table('module:company:short_name'), data: [
                'inherit' => ['label' => true, 'help' => false],
                'properties' => ['help' => 'A trading name'],
            ])->assertHasNoActionErrors();
        $this->assertSame(['help' => 'A trading name'], app(FieldOverrides::class)->properties('company', 'short_name'));
    }

    public function test_module_field_view_is_read_only_and_custom_fields_keep_their_urls(): void
    {
        $custom = CustomField::create(['model_type' => 'company', 'name' => 'gate_code', 'label' => 'Gate code', 'type' => FieldType::Text]);
        $page = Livewire::test(ListCustomFields::class)->filterTable('model_type', 'company');
        $page->mountAction(TestAction::make('view')->table('module:company:short_name'))->assertSuccessful();
        $page->unmountAction();
        $table = $page->instance()->getTable();
        $row = $page->instance()->getTableRecord('custom:'.$custom->id);
        $this->assertStringContainsString('/'.$custom->id, $table->getRecordUrl($row));
    }

    public function test_database_constraints_cannot_be_bypassed_by_a_field_declaration(): void
    {
        $properties = app(FieldOverrides::class)->editable(Company::class, Field::text('company_name'));
        $this->assertNotContains('required', $properties);
        $this->assertNotContains('show_in_form', $properties);
    }

    public function test_module_defaults_work_before_the_override_table_exists(): void
    {
        Schema::drop(FieldOverrides::TABLE);
        $this->assertSame('Short Name', $this->companyField('short_name')->getLabel());
    }

    public function test_non_admin_and_demo_writes_are_forbidden(): void
    {
        foreach (['employee', 'manager'] as $role) {
            $this->actingAs($this->userWithRole($role));
            Livewire::test(ListCustomFields::class)->assertForbidden();
            try {
                app(FieldOverrides::class)->save('company', 'short_name', ['label' => 'No']);
                $this->fail('Unauthorized write accepted.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->actingAs($this->userWithRole('super_admin'));
        config(['demo.enabled' => true]);
        Livewire::test(ListCustomFields::class)->assertForbidden();
        $this->assertSame(0, DB::table(FieldOverrides::TABLE)->count());
    }

    private function companyField(string $name): Field
    {
        return collect(CompanyResource::resolvedFields())->firstWhere('name', $name);
    }

    public function test_drag_order_mixes_custom_and_module_fields_and_preserves_other_settings(): void
    {
        $custom = CustomField::create(['model_type' => 'company', 'name' => 'gate_code', 'label' => 'Gate code', 'type' => FieldType::Text]);
        app(FieldOverrides::class)->save('company', 'short_name', ['label' => 'Trading name']);
        $page = Livewire::test(ListCustomFields::class)->filterTable('model_type', 'company')->call('toggleTableReordering');
        $keys = $page->instance()->getTableRecords()->getCollection()->keys()->all();
        $order = array_values(array_diff($keys, ['custom:'.$custom->id, 'module:company:short_name']));
        array_unshift($order, 'module:company:short_name', 'custom:'.$custom->id);
        $page->call('reorderTable', $order)->assertHasNoErrors();
        $this->assertSame($order, $page->instance()->getTableRecords()->getCollection()->keys()->all());
        $resolved = CompanyResource::resolvedFields();
        $this->assertSame(['short_name', $custom->column], array_column(array_slice($resolved, 0, 2), 'name'));
        $this->assertSame('Trading name', $resolved[0]->getLabel());
        $this->assertSame(10, $custom->fresh()->position);
        $custom->refresh()->update(['position' => 10000]);
        $this->assertSame($custom->column, collect(CompanyResource::resolvedFields())->last()->name);
    }

    public function test_reorder_mode_shows_all_fields_and_ignores_a_previous_label_sort(): void
    {
        for ($i = 0; $i < 18; $i++) {
            CustomField::create(['model_type' => 'company', 'name' => 'extra_'.$i, 'label' => 'Extra '.$i, 'type' => FieldType::Text]);
        }
        $page = Livewire::test(ListCustomFields::class)->filterTable('model_type', 'company')
            ->sortTable('label', 'desc')->set('tableRecordsPerPage', 5)->call('toggleTableReordering');
        $records = $page->instance()->getTableRecords();
        $this->assertSame(count(CompanyResource::fields()) + 18, $records->count());
        $this->assertSame('company_name', $records->first()['name']);
    }

    public function test_reordering_rejects_partial_duplicate_or_foreign_recordset_rows_atomically(): void
    {
        $keys = array_map(fn (Field $field): string => 'module:company:'.$field->name, CompanyResource::fields());
        $duplicate = $keys;
        $duplicate[1] = $duplicate[0];
        $foreign = $keys;
        $foreign[0] = 'module:contact:first_name';
        foreach ([array_slice($keys, 1), $duplicate, $foreign] as $order) {
            try {
                app(FieldOverrides::class)->reorder('company', $order);
                $this->fail('Invalid order was accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount(FieldOverrides::TABLE, 0);
            }
        }
    }

    public function test_reorder_uses_the_default_recordset_and_requires_no_search_or_origin_filter(): void
    {
        $page = Livewire::test(ListCustomFields::class);
        $this->assertTrue($page->instance()->canReorderFields());
        $page->filterTable('model_type', 'company');
        $this->assertTrue($page->instance()->canReorderFields());
        $page->searchTable('name');
        $this->assertFalse($page->instance()->canReorderFields());
        $page->searchTable('')->filterTable('origin', 'Module');
        $this->assertFalse($page->instance()->canReorderFields());
        config(['demo.enabled' => true]);
        $this->expectException(HttpException::class);
        app(FieldOverrides::class)->reorder('company', []);
    }
}
