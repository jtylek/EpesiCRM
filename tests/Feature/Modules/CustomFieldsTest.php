<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\Notes\Models\Note;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_added_field_gets_a_column_and_saves_like_a_shipped_one(): void
    {
        // Nothing from the install's own fields (or an earlier test's) leaks in.
        $this->assertSame([], CustomFieldRegistry::columnsFor(Note::class));

        $field = CustomField::create([
            'model_type' => 'note',
            'name' => 'follow_up_on',
            'label' => 'Follow up on',
            'type' => FieldType::Date,
        ]);

        $this->assertSame([$field->column], CustomFieldRegistry::columnsFor(Note::class));

        $note = Note::create(['title' => 'Call back', 'content' => 'Re: quote', $field->column => '2026-10-01']);

        $this->assertSame('2026-10-01', $note->fresh()->{$field->column}->toDateString());
    }

    public function test_the_suite_leaves_the_installs_cache_file_alone(): void
    {
        $path = CustomFieldRegistry::cachePath();
        $before = is_file($path) ? file_get_contents($path) : null;

        // A name no earlier run can have written, so any write shows.
        CustomField::create([
            'model_type' => 'note',
            'name' => 'probe_'.Str::lower(Str::random(8)),
            'label' => 'Probe',
            'type' => FieldType::Text,
        ]);

        $this->assertSame($before, is_file($path) ? file_get_contents($path) : null);
    }
}
