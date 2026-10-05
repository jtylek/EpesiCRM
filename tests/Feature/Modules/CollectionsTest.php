<?php

namespace Tests\Feature\Modules;

use App\Services\LegacyImport\Importers\ContactsImporter;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\CompanyResource;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\CreateContact;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\EditContact;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ListContacts;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldSchema;
use Epesi\Modules\RecordBrowser\Filament\RelationManagers\HistoryRelationManager;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages\CreateCustomField;
use Epesi\Modules\RecordBrowser\Models\Address;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Collections (FieldType::Collection): what a record has none or many of and
 * owns alone, starting with addresses — see "Collections" in
 * AI-shared/Epesi-custom-fields.md.
 */
class CollectionsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('main'));
    }

    public function test_the_form_saves_reorders_and_removes_items(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateContact::class)
            ->fillForm([
                'last_name' => 'Buyer',
                'first_name' => 'Ann',
                'addresses' => [
                    ['kind' => 'business', 'address_1' => 'Main St 1', 'city' => 'Warsaw', 'postal_code' => '00-001', 'country' => 'PL'],
                    ['kind' => 'home', 'city' => 'Kraków', 'country' => 'PL'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ann = ContactResource::getModel()::query()->where('last_name', 'Buyer')->sole();
        [$business, $home] = $ann->addresses->all();
        $this->assertSame(['business', 'home'], $ann->addresses->pluck('kind')->all());
        $this->assertSame([1, 2], $ann->addresses->pluck('position')->all());
        $this->assertSame('Main St 1, 00-001 Warsaw, Poland', $business->summary());

        // Dragging Home to the top makes it the primary one; the items are
        // updated, not made again.
        Livewire::test(EditContact::class, ['record' => $ann->getRouteKey()])
            ->assertFormSet(['addresses' => [
                ['id' => $business->id, 'kind' => 'business', 'address_1' => 'Main St 1', 'address_2' => null, 'city' => 'Warsaw', 'postal_code' => '00-001', 'country' => 'PL', 'zone' => null],
                ['id' => $home->id, 'kind' => 'home', 'address_1' => null, 'address_2' => null, 'city' => 'Kraków', 'postal_code' => null, 'country' => 'PL', 'zone' => null],
            ]])
            ->fillForm(['addresses' => [
                ['id' => $home->id, 'kind' => 'home', 'city' => 'Kraków', 'country' => 'PL'],
                ['id' => $business->id, 'kind' => 'business', 'address_1' => 'Main St 1', 'city' => 'Warsaw', 'postal_code' => '00-001', 'country' => 'PL'],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $ann->refresh();
        $this->assertSame([$home->id, $business->id], $ann->addresses->modelKeys());
        $this->assertSame([1, 2], $ann->addresses->pluck('position')->all());

        // Removing one deletes it; an id that isn't one of this field's items
        // makes a new item rather than taking someone else's.
        $other = Company::create(['company_name' => 'Acme Ltd']);
        $other->syncCollection('addresses', [['kind' => 'business', 'city' => 'Gdańsk', 'country' => 'PL']]);
        $foreign = $other->addresses->sole();

        Livewire::test(EditContact::class, ['record' => $ann->getRouteKey()])
            ->fillForm(['addresses' => [
                ['id' => $foreign->id, 'kind' => 'business', 'city' => 'Poznań', 'country' => 'PL'],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Poznań'], $ann->fresh()->addresses->pluck('city')->all());
        $this->assertNotSame($foreign->id, $ann->fresh()->addresses->sole()->id);
        $this->assertSame('Gdańsk', $foreign->fresh()->city);
        $this->assertSame(0, Address::query()->whereKey([$home->id, $business->id])->count());

        $undoRepeaterFake();
    }

    public function test_the_short_types_are_one_row_each(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();
        $contact = ContactResource::getModel()::create(['last_name' => 'Buyer', 'first_name' => 'Ann']);
        $contact->syncCollection('addresses', [['kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL']]);
        $contact->syncCollection('phones', [['kind' => 'work', 'value' => '+48 600 100 200']]);
        $contact->syncCollection('emails', [['kind' => 'work', 'value' => 'ann@buyer.test']]);
        $contact->syncCollection('online_accounts', [['kind' => 'website', 'value' => 'www.buyer.test']]);

        // The short types are a table repeater (CollectionItem::inlineRow()):
        // handle, kind, fields and delete on one line, nothing to collapse.
        $page = Livewire::test(EditContact::class, ['record' => $contact->getRouteKey()]);

        // E-mail, phone and online accounts; an address stays a card.
        $this->assertSame(3, substr_count($page->html(), '<thead>'));

        $undoRepeaterFake();
    }

    public function test_saved_items_start_collapsed_and_a_new_one_open(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();
        $contact = ContactResource::getModel()::create(['last_name' => 'Buyer', 'first_name' => 'Ann']);
        $contact->syncCollection('addresses', [['kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL']]);
        $saved = ['id' => $contact->addresses->sole()->id, 'kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL'];

        // The Collapse all / Expand all toggle starts from the same: all collapsed.
        $page = Livewire::test(EditContact::class, ['record' => $contact->getRouteKey()])
            ->assertSeeHtml('isCollapsed: true')
            ->assertSeeHtml('x-data="{ allCollapsed: true }"');
        // The page's sections are collapsibles too, open.
        $open = substr_count($page->html(), 'isCollapsed: false');

        $page->set('data.addresses', [$saved, ['kind' => 'home', 'city' => null, 'country' => null]])
            ->assertSeeHtml('isCollapsed: true');
        $this->assertSame($open + 1, substr_count($page->html(), 'isCollapsed: false'));

        $undoRepeaterFake();
    }

    public function test_an_item_needs_its_city_and_country(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateContact::class)
            ->fillForm([
                'last_name' => 'Buyer',
                'first_name' => 'Ann',
                'addresses' => [['kind' => 'business', 'address_1' => 'Main St 1']],
            ])
            ->call('create')
            ->assertHasFormErrors(['addresses.0.city' => 'required', 'addresses.0.country' => 'required']);

        $undoRepeaterFake();
    }

    public function test_zone_follows_the_country_of_its_own_item(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();
        $contact = ContactResource::getModel()::create(['last_name' => 'Buyer', 'first_name' => 'Ann']);
        $contact->syncCollection('addresses', [
            ['kind' => 'business', 'city' => 'Austin', 'country' => 'US', 'zone' => 'TX'],
            ['kind' => 'home', 'city' => 'Sydney', 'country' => 'AU', 'zone' => 'NSW'],
        ]);

        // Changing the second address's country clears its zone and leaves
        // the first one's alone.
        Livewire::test(EditContact::class, ['record' => $contact->getRouteKey()])
            ->set('data.addresses.1.country', 'CA')
            ->assertSet('data.addresses.1.zone', null)
            ->assertSet('data.addresses.0.zone', 'TX')
            ->set('data.addresses.1.zone', 'ON')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([['US', 'TX'], ['CA', 'ON']], $contact->fresh()->addresses->map(fn (Address $address): array => [$address->country, $address->zone])->all());
        $this->assertSame('Austin, Texas, United States', $contact->fresh()->addresses->first()->summary());

        $undoRepeaterFake();
    }

    public function test_history_shows_one_entry_per_changed_save(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateContact::class)
            ->fillForm([
                'last_name' => 'Buyer',
                'first_name' => 'Ann',
                'addresses' => [['kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ann = ContactResource::getModel()::query()->where('last_name', 'Buyer')->sole();
        $entries = fn () => $ann->activities()->orderBy('id')->get();

        // Created: one entry, its addresses in it.
        $this->assertCount(1, $entries());
        $this->assertSame(['Business: Warsaw, Poland'], $entries()->last()->properties['attributes']['addresses']);

        // The title and an address together: still one entry. The Edit page
        // saves the addresses before the contact, so the contact's change
        // joins the addresses' entry.
        $business = $ann->addresses->sole();

        Livewire::test(EditContact::class, ['record' => $ann->getRouteKey()])
            ->fillForm([
                'title' => 'CEO',
                'addresses' => [
                    ['id' => $business->id, 'kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL'],
                    ['kind' => 'home', 'address_1' => 'Long St 2', 'city' => 'Kraków', 'country' => 'PL'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertCount(2, $entries());
        $updated = $entries()->last();
        $this->assertSame('updated', $updated->event);
        $this->assertSame(['title', 'addresses'], array_keys($updated->properties['attributes']));
        $this->assertSame(['Business: Warsaw, Poland'], $updated->properties['old']['addresses']);
        $this->assertSame(['Business: Warsaw, Poland', 'Home: Long St 2, Kraków, Poland'], $updated->properties['attributes']['addresses']);

        // A save that changes nothing logs nothing.
        Livewire::test(EditContact::class, ['record' => $ann->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertCount(2, $entries());

        // The addresses alone: an entry of their own.
        $ann->syncCollection('addresses', [['kind' => 'home', 'address_1' => 'Long St 2', 'city' => 'Kraków', 'country' => 'PL']]);
        $this->assertCount(3, $entries());
        $this->assertSame(['addresses'], array_keys($entries()->last()->properties['attributes']));

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $ann->fresh(), 'pageClass' => ViewContact::class])
            ->assertSeeText('Addresses: Business: Warsaw, Poland')
            ->assertSeeHtml('<ins class="epesi-history-new">+ Home: Long St 2, Kraków, Poland</ins>')
            ->assertSeeHtml('<del class="epesi-history-old">− Business: Warsaw, Poland</del>')
            ->assertSeeText('Title: - → CEO');

        $undoRepeaterFake();
    }

    public function test_a_force_delete_removes_the_items_a_soft_delete_keeps_them(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $company = Company::create(['company_name' => 'Acme Ltd']);
        $company->syncCollection('addresses', [
            ['kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL'],
            ['kind' => 'shipping', 'city' => 'Łódź', 'country' => 'PL'],
            // Nothing but a kind is no item.
            ['kind' => 'other', 'city' => ' '],
        ]);
        $this->assertSame(2, Address::query()->count());

        $company->delete();
        $this->assertSame(2, Address::query()->count());

        $company->restore();
        $this->assertCount(2, $company->fresh()->addresses);

        $company->forceDelete();
        $this->assertSame(0, Address::query()->count());
    }

    public function test_the_view_page_and_the_list_show_the_items(): void
    {
        $this->withoutVite();
        $this->actingAs($this->userWithRole('employee'));
        $model = ContactResource::getModel();

        $ann = $model::create(['last_name' => 'Anders', 'first_name' => 'Ann']);
        $ann->syncCollection('addresses', [
            ['kind' => 'business', 'address_1' => 'Main St 1', 'city' => 'Warsaw', 'country' => 'PL'],
            ['kind' => 'home', 'city' => 'Kraków', 'country' => 'PL'],
        ]);
        $bob = $model::create(['last_name' => 'Brown', 'first_name' => 'Bob']);
        $bob->syncCollection('addresses', [['kind' => 'business', 'city' => 'Berlin', 'country' => 'DE']]);
        $cy = $model::create(['last_name' => 'Carter', 'first_name' => 'Cy']);

        $this->get(ContactResource::getUrl('view', ['record' => $ann]))
            ->assertOk()
            ->assertSeeText('Main St 1, Warsaw, Poland')
            ->assertSeeText('Kraków, Poland')
            ->assertSeeText('Home');

        // The column is the first address's city, sorted by it; the search
        // box finds a contact by any address; a filter by country or by
        // having one at all.
        $list = Livewire::test(ListContacts::class)
            ->assertCanSeeTableRecords([$ann, $bob, $cy])
            ->assertTableColumnStateSet('addresses', 'Warsaw', $ann)
            ->assertTableColumnStateSet('addresses', 'Berlin', $bob);

        // One render loads the page's addresses in one query, not one per row.
        $addressQueries = 0;
        DB::listen(function ($query) use (&$addressQueries): void {
            if (str_contains($query->sql, 'epesi_recordbrowser_addresses')) {
                $addressQueries++;
            }
        });

        $list->call('$refresh')->assertSee('Berlin');
        $this->assertSame(1, $addressQueries);

        $list->sortTable('addresses')
            ->assertCanSeeTableRecords([$cy, $bob, $ann], inOrder: true)
            ->searchTable('Kraków')
            ->assertCanSeeTableRecords([$ann])
            ->assertCanNotSeeTableRecords([$bob, $cy])
            ->searchTable(null)
            ->filterTable('addresses', ['country' => ['DE']])
            ->assertCanSeeTableRecords([$bob])
            ->assertCanNotSeeTableRecords([$ann, $cy])
            ->filterTable('addresses', ['has' => '0'])
            ->assertCanSeeTableRecords([$cy])
            ->assertCanNotSeeTableRecords([$ann, $bob])
            ->filterTable('addresses', ['kinds' => ['home']])
            ->assertCanSeeTableRecords([$ann])
            ->assertCanNotSeeTableRecords([$bob, $cy]);

        // Global search finds a record by any of its items.
        $this->assertContains('addresses.city', ContactResource::getGloballySearchableAttributes());
        $this->assertSame(['Ann Anders'], ContactResource::getGlobalSearchResults('Kraków')->map(fn ($result): string => (string) $result->title)->values()->all());
    }

    public function test_a_custom_field_on_address_shows_on_every_owner(): void
    {
        $this->withoutVite();
        $this->actingAs($this->userWithRole('employee'));

        $field = CustomField::create([
            'model_type' => 'address',
            'name' => 'gate_code',
            'label' => 'Gate code',
            'type' => FieldType::Text,
        ]);

        $this->assertTrue(Schema::hasColumn((new Address)->getTable(), $field->column));

        $company = Company::create(['company_name' => 'Acme Ltd']);
        $company->syncCollection('addresses', [['kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL', $field->column => '4412']]);
        $contact = ContactResource::getModel()::create(['last_name' => 'Buyer', 'first_name' => 'Ann']);
        $contact->syncCollection('addresses', [['kind' => 'home', 'city' => 'Kraków', 'country' => 'PL', $field->column => '17']]);

        $this->get(CompanyResource::getUrl('view', ['record' => $company]))->assertOk()->assertSeeText('Gate code: 4412');
        $this->get(ContactResource::getUrl('view', ['record' => $contact]))->assertOk()->assertSeeText('Gate code: 17');

        // It's on every address's card, and a change of it is logged.
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(EditContact::class, ['record' => $contact->getRouteKey()])
            ->assertFormFieldExists("addresses.0.{$field->column}")
            ->fillForm(['addresses' => [['id' => $contact->addresses->sole()->id, 'kind' => 'home', 'city' => 'Kraków', 'country' => 'PL', $field->column => '18']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Home: Kraków, Poland (Gate code: 18)'], $contact->activities()->latest('id')->first()->properties['attributes']['addresses']);

        $undoRepeaterFake();
    }

    public function test_administration_fields_adds_a_collection_and_holds_an_address_to_what_an_item_can_hold(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('administration'));
        $this->actingAs($this->userWithRole('super_admin'));

        foreach ([FieldType::Relation, FieldType::Related, FieldType::File, FieldType::Autonumber, FieldType::Collection] as $type) {
            Livewire::test(CreateCustomField::class)
                ->fillForm(['model_type' => 'address', 'type' => $type->value, 'label' => 'Extra', 'name' => 'extra'])
                ->call('create')
                ->assertHasFormErrors(['type']);
        }

        Livewire::test(CreateCustomField::class)
            ->fillForm(['model_type' => 'company', 'type' => FieldType::Collection->value, 'label' => 'Delivery points', 'name' => 'delivery_points', 'params' => ['collection' => 'address']])
            ->call('create')
            ->assertHasNoFormErrors();

        $field = CustomField::query()->where('name', 'delivery_points')->sole();
        $this->assertSame('address', $field->params['collection']);
        $this->assertSame(Address::class, CompanyResource::resolvedFields()[array_key_last(CompanyResource::resolvedFields())]->collectionType());
    }

    public function test_an_administrator_added_collection_needs_no_ddl_and_drops_its_items(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $columns = Schema::getColumnListing('companies');

        $field = CustomField::create([
            'model_type' => 'company',
            'name' => 'delivery_points',
            'label' => 'Delivery points',
            'type' => FieldType::Collection,
            'params' => ['collection' => 'address'],
        ]);

        $this->assertSame($columns, Schema::getColumnListing('companies'));

        $company = Company::create(['company_name' => 'Acme Ltd']);
        $company->syncCollection('addresses', [['kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL']]);
        $company->syncCollection($field->column, [
            ['kind' => 'shipping', 'city' => 'Łódź', 'country' => 'PL'],
            ['kind' => 'shipping', 'city' => 'Gdańsk', 'country' => 'PL'],
        ]);

        // Two collections of one type, kept apart by field.
        $this->assertSame(['Warsaw'], $company->addresses->pluck('city')->all());
        $this->assertSame(['Łódź', 'Gdańsk'], $company->{$field->column}->pluck('city')->all());

        app(CustomFieldSchema::class)->drop($field);
        $this->assertSame(['Warsaw'], Address::query()->pluck('city')->all());
    }

    public function test_the_migration_moves_both_of_a_contacts_addresses(): void
    {
        $migration = require base_path('modules/Epesi/CRM/Contacts/database/migrations/2026_09_28_140100_move_contact_addresses_into_items.php');

        // The columns as they were.
        $migration->down();
        $this->assertTrue(Schema::hasColumn('contacts', 'home_city'));

        $row = fn (string $last, array $values): int => DB::table('contacts')->insertGetId([
            'last_name' => $last, 'first_name' => 'X', 'permission' => 0, 'created_at' => now(), 'updated_at' => now(), ...$values,
        ]);

        $both = $row('Both', [
            'address_1' => 'Main St 1', 'city' => 'Warsaw', 'postal_code' => '00-001', 'country' => 'PL', 'zone' => '',
            'home_address_1' => 'Long St 2', 'home_city' => 'Kraków', 'home_country' => 'PL', 'home_zone' => 'MP',
        ]);
        // Legacy filled Country and Zone in on every record: not an address.
        $defaults = $row('Defaults', ['country' => 'PL', 'zone' => 'MP', 'home_country' => 'PL', 'home_zone' => 'MP']);
        $homeOnly = $row('HomeOnly', ['home_postal_code' => '30-001']);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('contacts', 'home_city'));
        $this->assertFalse(Schema::hasColumn('contacts', 'address_1'));

        $items = fn (int $id): array => DB::table('epesi_recordbrowser_addresses')
            ->where('owner_type', 'contact')->where('owner_id', $id)->where('field', 'addresses')
            ->orderBy('position')->get(['kind', 'position', 'address_1', 'city', 'postal_code', 'country', 'zone'])
            ->map(fn ($item): array => (array) $item)->all();

        $this->assertSame([
            ['kind' => 'business', 'position' => 1, 'address_1' => 'Main St 1', 'city' => 'Warsaw', 'postal_code' => '00-001', 'country' => 'PL', 'zone' => null],
            ['kind' => 'home', 'position' => 2, 'address_1' => 'Long St 2', 'city' => 'Kraków', 'postal_code' => null, 'country' => 'PL', 'zone' => 'MP'],
        ], $items($both));
        $this->assertSame([], $items($defaults));
        $this->assertSame([['kind' => 'home', 'position' => 1, 'address_1' => null, 'city' => null, 'postal_code' => '30-001', 'country' => null, 'zone' => null]], $items($homeOnly));
    }

    public function test_the_import_makes_items_of_legacy_addresses(): void
    {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy');

        Schema::connection('legacy')->create('contact_data_1', function (Blueprint $t) {
            $t->integer('id');
            $t->dateTime('created_on');
            $t->integer('created_by')->nullable();
            $t->integer('active')->default(1);
            foreach (['last_name', 'first_name', 'company_name', 'related_companies', 'memo', 'group', 'title', 'work_phone', 'mobile_phone', 'fax', 'email',
                'web_address', 'address_1', 'address_2', 'city', 'country', 'zone', 'postal_code', 'permission', 'home_phone', 'home_address_1',
                'home_address_2', 'home_city', 'home_country', 'home_zone', 'home_postal_code', 'login', 'access'] as $f) {
                $t->text("f_{$f}")->nullable();
            }
        });
        DB::connection('legacy')->table('contact_data_1')->insert(['id' => 1, 'created_on' => '2020-01-01 10:00:00', 'f_last_name' => 'Buyer', 'f_first_name' => 'Ann',
            'f_address_1' => 'Main St 1', 'f_city' => 'Warsaw', 'f_country' => 'PL', 'f_home_city' => 'Kraków', 'f_home_country' => 'PL']);
        // Legacy filled Country and Zone in on every record: not an address.
        DB::connection('legacy')->table('contact_data_1')->insert(['id' => 2, 'created_on' => '2020-01-02 10:00:00', 'f_last_name' => 'Defaults', 'f_first_name' => 'Dan',
            'f_country' => 'PL', 'f_zone' => 'MP', 'f_home_country' => 'PL', 'f_home_zone' => 'MP']);

        activity()->disableLogging();
        (new ContactsImporter)->run(withHistory: false);
        $ann = ContactResource::getModel()::withTrashed()->where('legacy_id', 1)->sole();
        $ids = $ann->addresses->modelKeys();

        // Running it again updates the same items.
        (new ContactsImporter)->run(withHistory: false);
        activity()->enableLogging();

        $ann->refresh();
        $this->assertSame(['business', 'home'], $ann->addresses->pluck('kind')->all());
        $this->assertSame(['Warsaw', 'Kraków'], $ann->addresses->pluck('city')->all());
        $this->assertSame($ids, $ann->addresses->modelKeys());
        $this->assertCount(0, ContactResource::getModel()::withTrashed()->where('legacy_id', 2)->sole()->addresses);
    }

    public function test_recordset_check_knows_collections(): void
    {
        $this->artisan('recordset:check')->assertSuccessful();
    }
}
