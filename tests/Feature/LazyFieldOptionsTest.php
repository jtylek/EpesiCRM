<?php

namespace Tests\Feature;

use App\Enums\RecordPermission;
use Closure;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\CompanyResource;
use Epesi\Modules\RecordBrowser\Console\RecordsetCheckCommand;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Field::select()/multiselect() take a closure so a list read from the
 * database is looked up when a form or filter needs it, not when fields() is
 * declared (which happens on every request that touches the resource).
 */
class LazyFieldOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_declaring_a_field_does_not_run_the_options_closure(): void
    {
        $calls = 0;

        Field::select('series', function () use (&$calls): array {
            $calls++;

            return ['a' => 'A'];
        })->filterable()->label('Series');

        $this->assertSame(0, $calls);
    }

    public function test_the_closure_runs_once_for_form_and_filter(): void
    {
        $calls = 0;
        $field = Field::select('series', function () use (&$calls): array {
            $calls++;

            return ['a' => 'A', 'b' => 'B'];
        })->filterable();

        $select = $field->toFormComponent();
        $this->assertInstanceOf(Select::class, $select);
        $this->assertSame(['a' => 'A', 'b' => 'B'], $select->getOptions());
        $this->assertNotNull($field->toTableFilter());
        $this->assertSame(1, $calls);
    }

    public function test_an_enum_or_an_array_goes_to_the_select_unchanged_and_only_a_closure_stays_lazy(): void
    {
        // Filament keeps an enum select's form state as the enum case only when it is handed
        // the class itself; wrapping every list in a closure turned the state into the bare value.
        $given = fn (Field $field): mixed => (new ReflectionMethod($field, 'componentOptions'))->invoke($field);

        $this->assertSame(RecordPermission::class, $given(Field::select('permission', RecordPermission::class)));
        $this->assertSame(['a' => 'A'], $given(Field::select('letter', ['a' => 'A'])));
        $this->assertSame(RecordPermission::class, $given(Field::multiselect('permissions', RecordPermission::class)));
        $this->assertInstanceOf(Closure::class, $given(Field::select('series', fn (): array => ['a' => 'A'])));
    }

    public function test_recordset_check_flags_a_resource_whose_fields_query_the_database(): void
    {
        // Declared here, not at file level: module classes autoload only once the app has booted.
        $resource = new class extends CompanyResource
        {
            public static function fields(): array
            {
                DB::table('companies')->count();

                return parent::fields();
            }
        };
        $command = new class($resource::class) extends RecordsetCheckCommand
        {
            public function __construct(private string $resource)
            {
                parent::__construct();
            }

            protected function recordsetResources(): array
            {
                return [$this->resource];
            }
        };
        $command->setLaravel($this->app);
        $output = new BufferedOutput;

        $status = $command->run(new ArrayInput([]), $output);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('database query', $output->fetch());
    }
}
