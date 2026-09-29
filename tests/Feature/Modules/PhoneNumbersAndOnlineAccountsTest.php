<?php

namespace Tests\Feature\Modules;

use App\Services\LegacyImport\Importers\CompaniesImporter;
use App\Services\LegacyImport\Importers\ContactsImporter;
use App\Services\LegacyImport\Importers\PhoneCallsImporter;
use Epesi\Modules\CommonData\CommonDataRepository;
use Epesi\Modules\CommonData\Facades\CommonData;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\CreateContact;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ListContacts;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\Pages\CreatePhoneCall;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\RecordBrowser\Models\OnlineAccount;
use Epesi\Modules\RecordBrowser\Models\PhoneNumber;
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
 * Phone numbers, with the messengers that reach each, and online accounts:
 * the collections that replaced a contact's and a company's phone, fax and
 * web address columns — step 6 of the plan in AI-shared/Epesi-custom-fields.md.
 */
class PhoneNumbersAndOnlineAccountsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('main'));
    }

    public function test_the_list_has_work_and_mobile_columns_and_finds_a_number_by_its_digits(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $ann = Contact::create(['last_name' => 'Anders', 'first_name' => 'Ann']);
        $ann->syncCollection('phones', [
            ['kind' => 'mobile', 'value' => '+48 600 100 200'],
            ['kind' => 'work', 'value' => '+48 22 555 01 01'],
            ['kind' => 'home', 'value' => '+48 12 333 44 55'],
        ]);
        $bob = Contact::create(['last_name' => 'Brown', 'first_name' => 'Bob']);
        $bob->syncCollection('phones', [['kind' => 'work', 'value' => '+49 30 1234567']]);

        $this->assertSame('48600100200', $ann->phones->first()->digits);

        Livewire::test(ListContacts::class)
            ->assertTableColumnStateSet('phones_work', '+48 22 555 01 01', $ann)
            ->assertTableColumnStateSet('phones_mobile', '+48 600 100 200', $ann)
            ->assertTableColumnStateSet('phones_work', '+49 30 1234567', $bob)
            // The digits alone, or spaced another way, find a number, and
            // not only the first.
            ->searchTable('600100200')
            ->assertCanSeeTableRecords([$ann])
            ->assertCanNotSeeTableRecords([$bob])
            ->searchTable('12-333-44-55')
            ->assertCanSeeTableRecords([$ann])
            ->assertCanNotSeeTableRecords([$bob])
            ->searchTable(null)
            ->sortTable('phones_work', 'desc')
            ->assertCanSeeTableRecords([$bob, $ann], inOrder: true);

        $this->assertContains('phones.digits', ContactResource::getGloballySearchableAttributes());
        $this->assertSame(['Ann Anders'], ContactResource::getGlobalSearchResults('600100200')->map(fn ($result): string => (string) $result->title)->values()->all());
    }

    public function test_a_messenger_opens_its_app_on_the_number(): void
    {
        $number = new PhoneNumber(['value' => '+48 600-100-200', 'messengers' => ['whatsapp', 'signal', 'viber', 'telegram']]);

        $this->assertSame([
            ['label' => 'WhatsApp', 'url' => 'https://wa.me/48600100200'],
            ['label' => 'Signal', 'url' => 'https://signal.me/#p/+48600100200'],
            ['label' => 'Viber', 'url' => 'viber://chat?number=%2B48600100200'],
            ['label' => 'Telegram', 'url' => 'https://t.me/+48600100200'],
        ], $number->links());

        // 00 carries the country code as + does; without either, no link.
        $this->assertSame('https://wa.me/48600100200', (new PhoneNumber(['value' => '0048 600 100 200', 'messengers' => ['whatsapp']]))->links()[0]['url']);
        $this->assertNull((new PhoneNumber(['value' => '600 100 200', 'messengers' => ['whatsapp']]))->links()[0]['url']);

        // A messenger an administrator adds is a badge without a link.
        CommonData::set('Phone_Messengers/threema', 'Threema');
        $this->assertSame([['label' => 'Threema', 'url' => null]], (new PhoneNumber(['value' => '+48 600 100 200', 'messengers' => ['threema']]))->links());
    }

    public function test_the_view_page_links_the_messengers_and_the_accounts(): void
    {
        $this->withoutVite();
        $this->actingAs($this->userWithRole('employee'));

        $ann = Contact::create(['last_name' => 'Anders', 'first_name' => 'Ann']);
        $ann->syncCollection('phones', [['kind' => 'mobile', 'value' => '+48 600 100 200', 'messengers' => ['whatsapp', 'signal']]]);
        $ann->syncCollection('online_accounts', [
            ['kind' => 'website', 'value' => 'www.example.com'],
            ['kind' => 'linkedin', 'value' => 'ann-anders'],
        ]);

        $this->get(ContactResource::getUrl('view', ['record' => $ann]))
            ->assertOk()
            ->assertSeeText('+48 600 100 200')
            ->assertSeeText('Mobile')
            ->assertSee('href="https://wa.me/48600100200"', false)
            ->assertSee('href="https://signal.me/#p/+48600100200"', false)
            ->assertSee('href="https://www.example.com"', false)
            ->assertSee('href="https://www.linkedin.com/in/ann-anders"', false)
            ->assertSeeText('LinkedIn');
    }

    public function test_the_form_warns_of_a_messenger_on_a_number_without_its_country_code(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();

        $warning = 'The links need + and the country code';

        Livewire::test(CreateContact::class)
            ->fillForm(['last_name' => 'Anders', 'first_name' => 'Ann', 'phones' => [['kind' => 'mobile', 'value' => '600 100 200', 'messengers' => ['whatsapp']]]])
            ->assertSee($warning)
            ->fillForm(['phones' => [['kind' => 'mobile', 'value' => '+48 600 100 200', 'messengers' => ['whatsapp']]]])
            ->assertDontSee($warning)
            // A warning, not an error: the number saves either way.
            ->fillForm(['phones' => [['kind' => 'mobile', 'value' => '600 100 200', 'messengers' => ['whatsapp']]]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['whatsapp'], Contact::query()->sole()->phones->sole()->messengers);

        $undoRepeaterFake();
    }

    public function test_an_online_account_links_by_its_service(): void
    {
        $link = fn (string $kind, string $handle): ?string => (new OnlineAccount(['kind' => $kind, 'value' => $handle]))->url();

        $this->assertSame('https://www.example.com', $link('website', 'www.example.com'));
        $this->assertSame('http://example.com/about', $link('website', 'http://example.com/about'));
        $this->assertSame('https://www.linkedin.com/in/ann-anders', $link('linkedin', 'ann-anders'));
        $this->assertSame('https://t.me/ann', $link('telegram', '@ann'));
        $this->assertSame('https://teams.microsoft.com/l/chat/0/0?users=ann%40example.com', $link('teams', 'ann@example.com'));
        $this->assertSame('https://www.facebook.com/ann', $link('facebook', 'ann'));
        $this->assertSame('https://x.com/ann', $link('x', '@ann'));
        $this->assertSame('https://www.instagram.com/ann', $link('instagram', 'ann'));
        $this->assertSame('https://github.com/ann', $link('github', 'ann'));

        // A URL links to itself whatever the service, with or without its scheme.
        $this->assertSame('https://www.linkedin.com/company/acme', $link('linkedin', 'https://www.linkedin.com/company/acme'));
        $this->assertSame('https://linkedin.com/in/ann', $link('linkedin', 'linkedin.com/in/ann'));

        // A service an administrator adds has no pattern: plain text, unless a URL.
        $this->assertNull($link('discord', 'ann'));
        $this->assertSame('https://discord.gg/acme', $link('discord', 'https://discord.gg/acme'));
    }

    public function test_the_github_migration_adds_it_once_and_needs_the_list(): void
    {
        $migration = require base_path('modules/Epesi/RecordBrowser/database/migrations/2026_09_29_100000_add_github_to_online_account_kinds.php');

        $github = fn () => DB::table('common_data')->where('path', 'Online_Account_Kinds/github');
        $parentId = DB::table('common_data')->where('path', 'Online_Account_Kinds')->value('id');

        // An install that already ran create_phone_and_online_account_lists.php
        // without GitHub in it: remove the entry this migration added, as if
        // it had never run.
        $github()->delete();
        CommonDataRepository::invalidate();
        $this->assertArrayNotHasKey('github', CommonData::array('Online_Account_Kinds'));
        $lastPosition = (int) DB::table('common_data')->where('parent_id', $parentId)->max('position');

        $migration->up();

        $this->assertSame('GitHub', $github()->value('value'));
        $this->assertTrue((bool) $github()->value('readonly'));
        $this->assertSame($lastPosition + 1, $github()->value('position'));
        $this->assertSame('GitHub', CommonData::array('Online_Account_Kinds')['github']);

        // Running it again doesn't duplicate the entry.
        $migration->up();
        $this->assertSame(1, $github()->count());

        // A database where the list itself doesn't exist yet is left alone,
        // not written into as a sibling of nothing.
        DB::table('common_data')->where('path', 'like', 'Online_Account_Kinds%')->delete();
        $migration->up();
        $this->assertSame(0, DB::table('common_data')->where('path', 'like', 'Online_Account_Kinds%')->count());
    }

    public function test_an_online_account_needs_its_service(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateContact::class)
            ->fillForm(['last_name' => 'Anders', 'first_name' => 'Ann', 'online_accounts' => [['value' => 'ann']]])
            ->call('create')
            ->assertHasFormErrors(['online_accounts.0.kind' => 'required']);

        $undoRepeaterFake();
    }

    public function test_an_online_accounts_handle_is_checked_against_its_service(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $undoRepeaterFake = Repeater::fake();

        $test = Livewire::test(CreateContact::class)
            ->fillForm(['last_name' => 'Anders', 'first_name' => 'Ann', 'online_accounts' => [
                ['kind' => 'website', 'value' => 'not a domain at all'],
                ['kind' => 'teams', 'value' => 'not an e-mail address'],
            ]])
            ->call('create')
            ->assertHasFormErrors(['online_accounts.0.value', 'online_accounts.1.value']);

        // A handle that only looks plausible — not a real X account — is not
        // rejected: nothing but X itself could tell the two apart.
        $test->fillForm(['online_accounts' => [
            ['kind' => 'website', 'value' => 'example.com'],
            ['kind' => 'teams', 'value' => 'ann@example.com'],
            ['kind' => 'x', 'value' => 'jdfksdfj'],
            ['kind' => 'x', 'value' => 'not a handle'],
        ]])
            ->call('create')
            ->assertHasNoFormErrors(['online_accounts.0.value', 'online_accounts.1.value', 'online_accounts.2.value'])
            ->assertHasFormErrors(['online_accounts.3.value']);

        $undoRepeaterFake();
    }

    public function test_history_names_the_messengers(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        // Each save on an instance of its own, as each request loads one:
        // one instance's saves make one entry (SaveActivity).
        $ann = Contact::create(['last_name' => 'Anders', 'first_name' => 'Ann']);
        $ann->fresh()->syncCollection('phones', [['kind' => 'mobile', 'value' => '+48 600 100 200']]);
        $ann->fresh()->syncCollection('phones', [['id' => $ann->phones->sole()->id, 'kind' => 'mobile', 'value' => '+48 600 100 200', 'messengers' => ['whatsapp', 'signal']]]);

        $entry = $ann->activities()->latest('id')->first();
        $this->assertSame(['Mobile: +48 600 100 200'], $entry->properties['old']['phones']);
        $this->assertSame(['Mobile: +48 600 100 200 (WhatsApp, Signal)'], $entry->properties['attributes']['phones']);
    }

    public function test_a_phone_call_suggests_the_contacts_and_the_companys_numbers(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $acme = Company::create(['company_name' => 'Acme Ltd']);
        $acme->syncCollection('phones', [['kind' => 'work', 'value' => '+48 22 000 00 00']]);
        $ann = Contact::create(['last_name' => 'Anders', 'first_name' => 'Ann', 'company_id' => $acme->id]);
        $ann->syncCollection('phones', [
            ['kind' => 'mobile', 'value' => '+48 600 100 200'],
            ['kind' => 'work', 'value' => '+48 22 555 01 01'],
        ]);

        Livewire::test(CreatePhoneCall::class)
            ->assertDontSeeHtml('<datalist')
            ->fillForm(['contact_id' => $ann->id, 'company_id' => $acme->id])
            ->assertSeeHtml('-numbers"><option value="+48 600 100 200" label="Mobile"></option><option value="+48 22 555 01 01" label="Work"></option><option value="+48 22 000 00 00" label="Acme Ltd: Work"></option></datalist>');
    }

    public function test_the_migrations_move_the_phones_and_the_web_address(): void
    {
        $contacts = require base_path('modules/Epesi/CRM/Contacts/database/migrations/2026_09_28_160100_move_contact_phones_and_web_address_into_items.php');
        $companies = require base_path('modules/Epesi/CRM/Companies/database/migrations/2026_09_28_160000_move_company_phones_and_web_address_into_items.php');

        // The columns as they were.
        $contacts->down();
        $companies->down();
        $this->assertTrue(Schema::hasColumn('contacts', 'work_phone'));
        $this->assertTrue(Schema::hasColumn('companies', 'web_address'));

        $row = fn (string $table, array $values): int => DB::table($table)->insertGetId(['permission' => 0, 'created_at' => now(), 'updated_at' => now(), ...$values]);

        $ann = $row('contacts', ['last_name' => 'Anders', 'first_name' => 'Ann', 'work_phone' => '+48 22 555 01 01', 'mobile_phone' => ' ', 'home_phone' => '12 333', 'web_address' => 'www.example.com']);
        $bob = $row('contacts', ['last_name' => 'Brown', 'first_name' => 'Bob']);
        $acme = $row('companies', ['company_name' => 'Acme Ltd', 'phone' => '+48 22 000 00 00', 'fax' => '+48 22 000 00 01', 'web_address' => 'https://acme.test']);

        $contacts->up();
        $companies->up();

        foreach (['work_phone', 'mobile_phone', 'home_phone', 'fax', 'web_address'] as $column) {
            $this->assertFalse(Schema::hasColumn('contacts', $column));
        }
        foreach (['phone', 'fax', 'web_address'] as $column) {
            $this->assertFalse(Schema::hasColumn('companies', $column));
        }

        $items = fn (string $table, string $type, int $id, array $columns): array => DB::table($table)
            ->where('owner_type', $type)->where('owner_id', $id)
            ->orderBy('position')->get(['kind', 'position', ...$columns])
            ->map(fn ($item): array => (array) $item)->all();

        $this->assertSame([
            ['kind' => 'work', 'position' => 1, 'value' => '+48 22 555 01 01', 'digits' => '48225550101'],
            ['kind' => 'home', 'position' => 2, 'value' => '12 333', 'digits' => '12333'],
        ], $items('epesi_recordbrowser_phone_numbers', 'contact', $ann, ['value', 'digits']));
        $this->assertSame([['kind' => 'website', 'position' => 1, 'value' => 'www.example.com']], $items('epesi_recordbrowser_online_accounts', 'contact', $ann, ['value']));
        $this->assertSame([], $items('epesi_recordbrowser_phone_numbers', 'contact', $bob, ['value']));

        $this->assertSame([
            ['kind' => 'work', 'position' => 1, 'value' => '+48 22 000 00 00'],
            ['kind' => 'fax', 'position' => 2, 'value' => '+48 22 000 00 01'],
        ], $items('epesi_recordbrowser_phone_numbers', 'company', $acme, ['value']));
        $this->assertSame([['kind' => 'website', 'position' => 1, 'value' => 'https://acme.test']], $items('epesi_recordbrowser_online_accounts', 'company', $acme, ['value']));
    }

    public function test_the_import_makes_items_and_a_calls_number_follows_its_kind(): void
    {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy');
        $legacy = Schema::connection('legacy');

        $table = fn (string $name, array $fields) => $legacy->create($name, function (Blueprint $t) use ($fields) {
            $t->integer('id');
            $t->dateTime('created_on');
            $t->integer('created_by')->nullable();
            $t->integer('active')->default(1);
            foreach ($fields as $f) {
                $t->text("f_{$f}")->nullable();
            }
        });

        $table('company_data_1', ['company_name', 'short_name', 'phone', 'fax', 'email', 'web_address', 'memo', 'group', 'permission',
            'address_1', 'address_2', 'city', 'country', 'zone', 'postal_code', 'tax_id']);
        $table('contact_data_1', ['last_name', 'first_name', 'company_name', 'related_companies', 'memo', 'group', 'title', 'work_phone', 'mobile_phone', 'fax', 'email',
            'web_address', 'address_1', 'address_2', 'city', 'country', 'zone', 'postal_code', 'permission', 'home_phone', 'home_address_1',
            'home_address_2', 'home_city', 'home_country', 'home_zone', 'home_postal_code', 'login', 'access']);
        $table('phonecall_data_1', ['subject', 'description', 'status', 'priority', 'permission', 'date_and_time', 'customer',
            'other_customer', 'other_customer_name', 'phone', 'other_phone', 'other_phone_number', 'employees', 'related']);

        $db = DB::connection('legacy');
        $db->table('company_data_1')->insert(['id' => 3, 'created_on' => '2020-01-01 10:00:00', 'f_company_name' => 'Acme Ltd',
            'f_phone' => '+48 22 000 00 00', 'f_fax' => '+48 22 000 00 01', 'f_web_address' => 'acme.test']);
        $db->table('contact_data_1')->insert(['id' => 5, 'created_on' => '2020-01-01 10:00:00', 'f_last_name' => 'Anders', 'f_first_name' => 'Ann',
            'f_work_phone' => '+48 22 555 01 01', 'f_mobile_phone' => '+48 600 100 200', 'f_home_phone' => '', 'f_web_address' => 'ann.example.com']);
        // Legacy's phone choice: 1 mobile, 2 work, 3 home, 4 the company's.
        foreach ([1 => ['contact/5', 1], 2 => ['contact/5', 2], 3 => ['contact/5', 3], 4 => ['company/3', 4]] as $id => [$customer, $phone]) {
            $db->table('phonecall_data_1')->insert(['id' => $id, 'created_on' => '2020-01-02 10:00:00', 'f_subject' => "Call {$id}",
                'f_date_and_time' => '2020-01-02 10:00:00', 'f_customer' => $customer, 'f_phone' => (string) $phone]);
        }

        activity()->disableLogging();
        (new CompaniesImporter)->run(withHistory: false);
        (new ContactsImporter)->run(withHistory: false);
        $ann = Contact::withTrashed()->where('legacy_id', 5)->sole();
        $ids = $ann->phones->modelKeys();
        // Running it again updates the same items.
        (new ContactsImporter)->run(withHistory: false);
        (new PhoneCallsImporter)->run(withHistory: false);
        activity()->enableLogging();

        $ann->refresh();
        $this->assertSame([['work', '+48 22 555 01 01'], ['mobile', '+48 600 100 200']], $ann->phones->map(fn (PhoneNumber $phone): array => [$phone->kind, $phone->value])->all());
        $this->assertSame($ids, $ann->phones->modelKeys());
        $this->assertSame([['website', 'ann.example.com']], $ann->online_accounts->map(fn (OnlineAccount $account): array => [$account->kind, $account->value])->all());

        $acme = Company::withTrashed()->where('legacy_id', 3)->sole();
        $this->assertSame(['work', 'fax'], $acme->phones->pluck('kind')->all());
        $this->assertSame(['acme.test'], $acme->online_accounts->pluck('value')->all());

        $this->assertSame(
            [1 => '+48 600 100 200', 2 => '+48 22 555 01 01', 3 => null, 4 => '+48 22 000 00 00'],
            PhoneCall::withTrashed()->orderBy('legacy_id')->pluck('phone_number', 'legacy_id')->all(),
        );
    }
}
