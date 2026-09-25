<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\RecordBrowser\Recordset\Field;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class WebAddressFieldTest extends TestCase
{
    public function test_a_web_address_is_a_badge_with_a_link_icon_opening_in_a_new_tab(): void
    {
        $entry = Field::url('web_address')->toInfolistEntry();

        $this->assertInstanceOf(TextEntry::class, $entry);
        $this->assertTrue($entry->isBadge());
        $this->assertNotNull($entry->getIcon('epe.si'));
        $this->assertTrue($entry->shouldOpenUrlInNewTab());
    }

    public function test_the_list_column_is_the_same_badge_and_link(): void
    {
        $column = Field::url('web_address')->toTableColumn();

        $this->assertInstanceOf(TextColumn::class, $column);
        $this->assertTrue($column->isBadge());
        $this->assertNotNull($column->getIcon('epe.si'));
        $this->assertTrue($column->shouldOpenUrlInNewTab());
        $this->assertSame('https://www.arturia.com', $column->getUrl('www.arturia.com'));
        $this->assertSame('http://epe.si/x', $column->getUrl('http://epe.si/x'));
        $this->assertNull($column->getUrl(null));
    }

    public function test_it_links_out_even_when_stored_without_a_scheme(): void
    {
        $linkFor = fn (?string $state): ?string => Field::url('web_address')->toInfolistEntry()->getUrl($state);

        $this->assertSame('https://www.arturia.com', $linkFor('www.arturia.com'));
        $this->assertSame('http://epe.si/x', $linkFor('http://epe.si/x'));
        $this->assertSame('https://epe.si', $linkFor(' epe.si '));
        $this->assertNull($linkFor(null));
        $this->assertNull($linkFor(''));
    }

    public function test_a_value_that_is_not_http_never_becomes_a_link_of_its_own_scheme(): void
    {
        $url = Field::url('web_address')->toInfolistEntry()->getUrl('javascript:alert(1)');

        $this->assertSame('https://javascript:alert(1)', $url);
    }

    public function test_the_form_accepts_an_address_without_a_scheme_and_rejects_junk(): void
    {
        $input = Field::url('web_address')->toFormComponent();
        $this->assertInstanceOf(TextInput::class, $input);

        $rules = ['web_address' => collect($input->getValidationRules())->filter(fn ($rule) => is_string($rule))->all()];
        $passes = fn (string $value): bool => Validator::make(['web_address' => $value], $rules)->passes();

        $this->assertTrue($passes('www.arturia.com'));
        $this->assertTrue($passes('https://epe.si/path?a=1'));
        $this->assertTrue($passes('localhost:8080'));
        $this->assertFalse($passes('hello world'));
        $this->assertFalse($passes('javascript:alert(1)'));
    }
}
