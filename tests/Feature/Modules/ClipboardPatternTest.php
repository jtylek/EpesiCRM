<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CommonData\Facades\CommonData;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Recordset\ClipboardPattern;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ClipboardPattern's rendering and validation —
 * AI-shared/recordbrowser/clipboard.md's pattern syntax and keyword
 * grammar — against real Company/Contact records, so collections resolve
 * exactly as the View page's action will see them.
 */
class ClipboardPatternTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('main'));
    }

    public function test_a_block_is_dropped_whole_when_none_of_its_keys_has_a_value(): void
    {
        $company = Company::create(['company_name' => 'Acme Ltd']);

        $this->assertSame(
            'Acme Ltd',
            ClipboardPattern::renderPattern("{company_name}\n%{tel. {phones.work}}", $company, 'company'),
        );
    }

    public function test_a_block_is_kept_when_one_of_its_keys_has_a_value(): void
    {
        $company = Company::create(['company_name' => 'Acme Ltd']);
        $company->syncCollection('phones', [['kind' => 'work', 'value' => '+48 600 100 200']]);

        $this->assertSame(
            "Acme Ltd\ntel. +48 600 100 200",
            ClipboardPattern::renderPattern("{company_name}\n%{tel. {phones.work}}", $company, 'company'),
        );
    }

    public function test_a_plain_keyword_line_is_dropped_when_blank(): void
    {
        $company = Company::create(['company_name' => 'Acme Ltd']);

        $this->assertSame(
            'Acme Ltd',
            ClipboardPattern::renderPattern("{company_name}\n{short_name}", $company, 'company'),
        );
    }

    public function test_zero_is_not_treated_as_blank(): void
    {
        $company = Company::create(['company_name' => 'Acme Ltd', 'short_name' => '0']);

        $this->assertSame('0', ClipboardPattern::renderPattern('{short_name}', $company, 'company'));
    }

    public function test_collections_primary_item_and_item_field(): void
    {
        $contact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);
        $contact->syncCollection('emails', [
            ['kind' => 'work', 'value' => 'ann@buyer.test'],
            ['kind' => 'home', 'value' => 'ann@home.test'],
        ]);
        $contact->syncCollection('addresses', [['kind' => 'business', 'address_1' => 'Main St 1', 'city' => 'Warsaw', 'country' => 'PL']]);

        $this->assertSame('ann@buyer.test', ClipboardPattern::renderPattern('{emails}', $contact, 'contact'));
        $this->assertSame('Warsaw', ClipboardPattern::renderPattern('{addresses.city}', $contact, 'contact'));
    }

    public function test_collections_by_kind_and_kind_with_a_sub_field(): void
    {
        $contact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);
        $contact->syncCollection('phones', [
            ['kind' => 'work', 'value' => '+48 600 100 200'],
            ['kind' => 'fax', 'value' => '+48 600 100 201'],
        ]);
        $contact->syncCollection('addresses', [
            ['kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL'],
            ['kind' => 'home', 'city' => 'Kraków', 'country' => 'PL'],
        ]);

        $this->assertSame('+48 600 100 201', ClipboardPattern::renderPattern('{phones.fax}', $contact, 'contact'));
        $this->assertSame('Kraków', ClipboardPattern::renderPattern('{addresses.home.city}', $contact, 'contact'));
    }

    public function test_an_address_shows_country_and_zone_names_not_their_codes(): void
    {
        CommonData::seed('Countries/PL', ['MA' => 'Masovian']);

        $contact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);
        $contact->syncCollection('addresses', [
            ['kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL', 'zone' => 'MA'],
            ['kind' => 'home', 'city' => 'Kraków', 'country' => 'PL', 'zone' => 'MA'],
        ]);

        $this->assertSame(
            "Masovian\nPoland",
            ClipboardPattern::renderPattern("{addresses.zone}\n{addresses.country}", $contact, 'contact'),
        );
        $this->assertSame(
            "Masovian\nPoland",
            ClipboardPattern::renderPattern("{addresses.home.zone}\n{addresses.home.country}", $contact, 'contact'),
        );
    }

    public function test_a_zone_outside_the_countrys_list_stays_as_typed(): void
    {
        $contact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);
        $contact->syncCollection('addresses', [['kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL', 'zone' => 'Free text']]);

        $this->assertSame('Free text', ClipboardPattern::renderPattern('{addresses.zone}', $contact, 'contact'));
    }

    public function test_a_blank_zone_drops_its_block(): void
    {
        $contact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);
        $contact->syncCollection('addresses', [['kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL', 'postal_code' => '00-001']]);

        $this->assertSame(
            'Warsaw, 00-001',
            ClipboardPattern::renderPattern('%{{addresses.city}, }%{{addresses.zone} }{addresses.postal_code}', $contact, 'contact'),
        );
    }

    public function test_an_item_field_wins_over_a_same_named_kind(): void
    {
        $contact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);
        // The primary address is business/Warsaw; a "home" kind also exists,
        // but {addresses.city} must still read the primary item's own field.
        $contact->syncCollection('addresses', [
            ['kind' => 'business', 'city' => 'Warsaw', 'country' => 'PL'],
            ['kind' => 'home', 'city' => 'Kraków', 'country' => 'PL'],
        ]);

        $this->assertSame('Warsaw', ClipboardPattern::renderPattern('{addresses.city}', $contact, 'contact'));
    }

    public function test_an_unknown_keyword_renders_empty_and_drops_its_line(): void
    {
        $company = Company::create(['company_name' => 'Acme Ltd']);

        $this->assertSame(
            'Acme Ltd',
            ClipboardPattern::renderPattern("{company_name}\n{nonexistent_field}", $company, 'company'),
        );
    }

    public function test_a_hidden_field_is_excluded_from_rendering_and_the_keyword_list(): void
    {
        $contact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer', 'user_id' => null]);

        $this->assertArrayNotHasKey('user_id', ClipboardPattern::keywords('contact'));
        $this->assertSame('', ClipboardPattern::renderPattern('{user_id}', $contact, 'contact'));
    }

    public function test_repeated_whitespace_is_collapsed(): void
    {
        $company = Company::create(['company_name' => 'Acme   Ltd']);

        $this->assertSame('Acme Ltd', ClipboardPattern::renderPattern('{company_name}', $company, 'company'));
    }

    public function test_keywords_lists_plain_relation_and_collection_entries(): void
    {
        $keywords = ClipboardPattern::keywords('contact');

        $this->assertArrayHasKey('first_name', $keywords);
        $this->assertArrayHasKey('company_id', $keywords);
        $this->assertArrayHasKey('emails', $keywords);
        $this->assertArrayHasKey('addresses.city', $keywords);
        $this->assertArrayHasKey('phones.work', $keywords);
    }

    public function test_validate_rejects_a_nested_block(): void
    {
        $this->assertNotNull(ClipboardPattern::validate('company', '%{a %{b} c}'));
    }

    public function test_validate_rejects_an_unknown_keyword(): void
    {
        $this->assertNotNull(ClipboardPattern::validate('company', '{nonexistent_field}'));
    }

    public function test_validate_accepts_the_shipped_patterns(): void
    {
        $this->assertNull(ClipboardPattern::validate('company', implode("\n", [
            '{company_name}',
            '{addresses.address_1}',
            '{addresses.address_2}',
            '%{{addresses.city}, }%{{addresses.zone} }{addresses.postal_code}',
            '{addresses.country}',
            '%{tel. {phones.work}}',
            '%{fax. {phones.fax}}',
            '%{{online_accounts.website}}',
        ])));

        $this->assertNull(ClipboardPattern::validate('contact', implode("\n", [
            '%{{first_name} {last_name}}',
            '%{{title}}',
            '{company_id}',
            '{addresses.address_1}',
            '{addresses.address_2}',
            '%{{addresses.city}, }%{{addresses.zone} }{addresses.postal_code}',
            '{addresses.country}',
            '%{tel. {phones.work}}',
            '%{e-mail: {emails}}',
        ])));
    }

    public function test_render_follows_the_stored_override_and_falls_back_to_the_default_when_cleared(): void
    {
        $contact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);

        $default = ClipboardPattern::render($contact, 'contact');
        $this->assertStringContainsString('Ann Buyer', $default);

        ClipboardPattern::setPattern('contact', '{first_name}');
        $this->assertSame('Ann', ClipboardPattern::render($contact, 'contact'));

        ClipboardPattern::setPattern('contact', '');
        $this->assertSame($default, ClipboardPattern::render($contact, 'contact'));
    }
}
