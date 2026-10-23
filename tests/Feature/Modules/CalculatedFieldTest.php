<?php

namespace Tests\Feature\Modules;

use Closure;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * `->calculated()`: a read-only value worked out from the record's other
 * fields, with no column of its own, shown as its field type shows values.
 */
class CalculatedFieldTest extends TestCase
{
    use RefreshDatabase;

    private function nameLength(): Field
    {
        return Field::integer('name_length')->label('Name length')->calculated(
            fn (?Model $record, Closure $value): ?int => filled($value('company_name')) ? mb_strlen((string) $value('company_name')) : null,
            ['company_name'],
        );
    }

    public function test_it_keeps_no_column_and_no_cast(): void
    {
        $field = $this->nameLength();

        $this->assertTrue($field->isCalculated());
        $this->assertFalse($field->hasColumn());
        $this->assertNull($field->cast());
        $this->assertTrue(Field::integer('name_length')->hasColumn());
    }

    public function test_it_reads_the_fields_it_depends_on(): void
    {
        $this->assertSame(['company_name'], $this->nameLength()->calculatedFrom());
        $this->assertSame([], Field::text('company_name')->calculatedFrom());
    }

    public function test_only_a_scalar_field_can_be_calculated(): void
    {
        $this->expectException(LogicException::class);

        Field::linkedTo()->calculated(fn (): array => []);
    }

    public function test_the_list_column_doesnt_sort_search_or_filter(): void
    {
        $text = Field::text('label')->calculated(fn (): string => 'x');

        $this->assertFalse($text->toTableColumn()->isSortable());
        $this->assertFalse($text->toTableColumn()->isSearchable());
        $this->assertFalse($text->isSearchable());
        $this->assertNull($this->nameLength()->filterable()->toTableFilter());
    }

    public function test_the_form_shows_an_entry_that_is_never_submitted(): void
    {
        $component = $this->nameLength()->toFormComponent();

        $this->assertInstanceOf(TextEntry::class, $component);
        $this->assertFalse($component->isDehydrated());
    }

    public function test_administrators_may_hide_it_but_not_require_or_filter_it(): void
    {
        $properties = $this->nameLength()->administratorEditableProperties();

        $this->assertContains('show_in_form', $properties);
        $this->assertNotContains('required', $properties);
        $this->assertNotContains('filterable', $properties);
    }

    public function test_a_field_it_reads_turns_live_on_the_form(): void
    {
        $picker = Field::dateTime('started_at')->toFormComponent(live: true);
        $text = Field::text('company_name')->toFormComponent(live: true);

        $this->assertInstanceOf(DateTimePicker::class, $picker);
        $this->assertTrue($picker->isLive());
        $this->assertInstanceOf(TextInput::class, $text);
        $this->assertTrue($text->isLiveOnBlur());
    }
}
