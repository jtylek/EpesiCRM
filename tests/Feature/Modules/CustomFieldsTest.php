<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_added_field_gets_a_column_and_saves_like_a_shipped_one(): void
    {
        // Nothing from the install's own fields (or an earlier test's) leaks in.
        $this->assertSame([], CustomFieldRegistry::columnsFor(Company::class));

        $field = CustomField::create([
            'model_type' => 'company',
            'name' => 'follow_up_on',
            'label' => 'Follow up on',
            'type' => FieldType::Date,
        ]);

        $this->assertSame([$field->column], CustomFieldRegistry::columnsFor(Company::class));

        $company = Company::create(['company_name' => 'Acme Corp', $field->column => '2026-10-01']);

        $this->assertSame('2026-10-01', $company->fresh()->{$field->column}->toDateString());
    }

    public function test_an_autonumber_field_needs_no_column(): void
    {
        $field = CustomField::create([
            'model_type' => 'company',
            'name' => 'reference',
            'label' => 'Reference',
            'type' => FieldType::Autonumber,
            'params' => ['prefix' => 'C-', 'pad_length' => 3],
        ]);

        $this->assertFalse(Schema::hasColumn((new Company)->getTable(), $field->column));

        $company = Company::create(['company_name' => 'Acme Corp']);

        $built = CustomFieldRegistry::fieldsFor(Company::class)[0];
        $this->assertSame('C-'.str_pad((string) $company->id, 3, '0', STR_PAD_LEFT), $built->formatAutonumber($company->id));
    }

    public function test_an_autonumber_pads_with_its_pad_character(): void
    {
        CustomField::create([
            'model_type' => 'company',
            'name' => 'reference',
            'label' => 'Reference',
            'type' => FieldType::Autonumber,
            'params' => ['prefix' => '#', 'pad_length' => 5, 'pad_mask' => '*'],
        ]);

        $built = CustomFieldRegistry::fieldsFor(Company::class)[0];

        $this->assertSame('#***42', $built->formatAutonumber(42));
    }

    public function test_a_time_field_offers_times_its_minutes_interval_apart(): void
    {
        // As the admin form's select stores it: a string.
        CustomField::create([
            'model_type' => 'company',
            'name' => 'call_at',
            'label' => 'Call at',
            'type' => FieldType::DateTime,
            'params' => ['minutes_step' => '15'],
        ]);

        /** @var DateTimePicker $picker */
        $picker = CustomFieldRegistry::fieldsFor(Company::class)[0]->toFormComponent();

        $this->assertSame(15, $picker->getMinutesStep());
        // The native picker's `step`, in seconds.
        $this->assertSame(900, $picker->getStep());

        /** @var TimePicker $time */
        $time = Field::time('at')->minutesStep(60)->toFormComponent();
        $this->assertSame(3600, $time->getStep());

        $this->assertSame(300, Field::time('at')->toFormComponent()->getStep());
        $this->assertSame(300, Field::dateTime('at')->toFormComponent()->getStep());
        $this->assertSame(300, DateTimePicker::make('at')->seconds(false)->getStep());
        $this->assertSame(300, TimePicker::make('at')->seconds(false)->getStep());
    }

    public function test_the_suite_leaves_the_installs_cache_file_alone(): void
    {
        $path = CustomFieldRegistry::cachePath();
        $before = is_file($path) ? file_get_contents($path) : null;

        // A name no earlier run can have written, so any write shows.
        CustomField::create([
            'model_type' => 'company',
            'name' => 'probe_'.Str::lower(Str::random(8)),
            'label' => 'Probe',
            'type' => FieldType::Text,
        ]);

        $this->assertSame($before, is_file($path) ? file_get_contents($path) : null);
    }
}
