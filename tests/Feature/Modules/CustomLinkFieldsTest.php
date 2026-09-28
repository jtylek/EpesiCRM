<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CommonData\Facades\CommonData;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\CompanyResource;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\EditCompany;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldSchema;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages\CreateCustomField;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Models\RecordLink;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Link and shared-list fields an administrator adds under Administration →
 * Fields: "Link to one record" (a key column), "Link to many records" (rows of
 * the shared link table) and "Shared list" (CommonData).
 */
class CustomLinkFieldsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_link_to_one_record_is_a_key_column_with_its_own_relationship(): void
    {
        $this->withoutVite();
        $this->actingAs($this->userWithRole('employee'));
        // As a request would: the related resource (its title column) comes from the panel.
        Filament::setCurrentPanel('main');

        $column = $this->addField(FieldType::Relation, ['recordset' => 'contact'])->column;
        $relationship = CustomFieldRegistry::relationshipName($column);

        $this->assertTrue(Schema::hasColumn((new Company)->getTable(), $column));

        $ann = Contact::create(['last_name' => 'Buyer', 'first_name' => 'Ann']);
        $bob = Contact::create(['last_name' => 'Seller', 'first_name' => 'Bob']);
        $company = Company::create(['company_name' => 'Acme Ltd', $column => $ann->id]);

        $this->assertTrue($company->fresh()->{$relationship}->is($ann));

        Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
            ->fillForm([$column => $bob->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($bob->id, (int) $company->fresh()->{$column});

        $this->get(CompanyResource::getUrl('view', ['record' => $company]))
            ->assertOk()
            ->assertSee('Seller');
    }

    public function test_a_link_to_many_records_keeps_its_links_in_the_shared_table(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel('main');

        $definition = $this->addField(FieldType::Relations, ['recordset' => 'contact']);
        $column = $definition->column;
        $relationship = CustomFieldRegistry::relationshipName($column);

        $this->assertFalse(Schema::hasColumn((new Company)->getTable(), $column));

        $ann = Contact::create(['last_name' => 'Buyer', 'first_name' => 'Ann']);
        $bob = Contact::create(['last_name' => 'Seller', 'first_name' => 'Bob']);
        $company = Company::create(['company_name' => 'Acme Ltd']);

        Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
            ->fillForm([$relationship => [$ann->id, $bob->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $company = $company->fresh();
        $this->assertEqualsCanonicalizing([$ann->id, $bob->id], $company->{$relationship}->modelKeys());
        // The same rows "link to any record" uses, held to this field and target.
        $this->assertSame(2, RecordLink::query()->where('field', $column)->where('target_type', 'contact')->count());
        $this->assertEqualsCanonicalizing([$ann->id, $bob->id], $company->linkedRecords($column)->modelKeys());

        app(CustomFieldSchema::class)->drop($definition);
        $this->assertSame(0, RecordLink::query()->where('field', $column)->count());
    }

    public function test_a_shared_list_field_is_one_key_or_several(): void
    {
        $this->withoutVite();
        $this->actingAs($this->userWithRole('employee'));
        CommonData::seed('Test_Sizes', ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large']);

        $one = $this->addField(FieldType::CommonData, ['array' => 'Test_Sizes'], 'size')->column;
        $several = $this->addField(FieldType::CommonData, ['array' => 'Test_Sizes', 'multiple' => true], 'sizes')->column;

        $company = Company::create(['company_name' => 'Acme Ltd', $one => 'm', $several => ['s', 'l']]);

        $fresh = $company->fresh();
        $this->assertSame('m', $fresh->{$one});
        $this->assertSame(['s', 'l'], $fresh->{$several});

        $this->get(CompanyResource::getUrl('view', ['record' => $company]))
            ->assertOk()
            ->assertSee('Medium')
            ->assertSee('Large');
    }

    public function test_the_administration_form_offers_the_link_types(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(CreateCustomField::class)
            ->fillForm([
                'model_type' => 'company',
                'type' => FieldType::Relation->value,
                'label' => 'Account manager',
                'name' => 'account_manager',
            ])
            ->fillForm(['params' => ['recordset' => 'contact']])
            ->call('create')
            ->assertHasNoFormErrors();

        $definition = CustomField::query()->where('name', 'account_manager')->sole();
        $this->assertSame(FieldType::Relation, $definition->type);
        $this->assertSame('contact', $definition->params['recordset']);
        $this->assertTrue(Schema::hasColumn((new Company)->getTable(), $definition->column));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function addField(FieldType $type, array $params, string $name = 'contacts'): CustomField
    {
        return CustomField::create([
            'model_type' => 'company',
            'name' => $name,
            'label' => ucfirst($name),
            'type' => $type,
            'params' => $params,
            'show_in_view' => true,
            'show_in_form' => true,
        ]);
    }
}
