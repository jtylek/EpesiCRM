<?php

namespace Epesi\Modules\RecordBrowser\Console;

use App\Models\Module;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Writes the files a recordset needs and the developer never edits: a model, a
 * migration, the resource with an empty `fields()`, and the four page stubs.
 *
 * The stubs are the reason this exists. A page class cannot be shared between
 * resources — Filament asks the *page class* for resource-level middleware
 * (`isEmailVerificationRequired()` and friends) while building routes, when
 * there is no request to disambiguate from, and `Page::route()` registers
 * against `static::class` — so the engine cannot remove the files. What it can
 * do is leave nothing in them: five lines naming a resource, emitted once and
 * never opened again.
 */
class MakeRecordsetCommand extends Command
{
    protected $signature = 'make:epesi-recordset
        {module : the module to add it to, e.g. Epesi/Notes}
        {name : singular model name, e.g. Invoice}';

    protected $description = 'Scaffold a recordset (model, migration, resource with fields(), page stubs) inside a module';

    public function handle(): int
    {
        $modulePath = trim(str_replace('\\', '/', (string) $this->argument('module')), '/');
        $name = Str::studly((string) $this->argument('name'));
        $plural = Str::plural($name);

        $module = Module::query()->where('path', $modulePath)->first();

        if (! $module) {
            $this->components->error("No module registered at modules/{$modulePath}. Run make:epesi-module first, then module:register.");

            return self::FAILURE;
        }

        $namespace = rtrim((string) $module->namespace, '\\');
        $directory = $module->directory();
        $resourceNamespace = "{$namespace}\\Filament\\Resources\\{$plural}";
        $resourceDirectory = "{$directory}/src/Filament/Resources/{$plural}";
        // Module tables carry the module's own slug so two modules can never
        // claim the same table name — and the migration filename inherits it,
        // which is what ModuleArchive requires of a distributable module.
        $slug = str_replace(['/', '-', '.'], '_', strtolower((string) $module->module_id));
        $table = $slug.'_'.Str::snake($plural);

        if (is_dir($resourceDirectory)) {
            $this->components->error("{$plural} already exists in {$modulePath}.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists("{$resourceDirectory}/Pages");
        File::ensureDirectoryExists("{$directory}/src/Models");
        File::ensureDirectoryExists("{$directory}/database/migrations");

        File::put("{$resourceDirectory}/{$name}Resource.php", $this->resource($resourceNamespace, $namespace, $name));

        foreach ($this->pages($name, $plural) as $file => $contents) {
            File::put("{$resourceDirectory}/Pages/{$file}.php", $contents($resourceNamespace, $name));
        }

        $modelFile = "{$directory}/src/Models/{$name}.php";

        if (! is_file($modelFile)) {
            File::put($modelFile, $this->model($namespace, $name, $table));
        }

        $migration = "{$directory}/database/migrations/".now()->format('Y_m_d_His')
            .'_create_'.$table.'_table.php';

        File::put($migration, $this->migration($table));

        $alias = Str::snake($name);

        $this->components->info("Created {$plural} in modules/{$modulePath}");
        $this->line("  Declare the fields in src/Filament/Resources/{$plural}/{$name}Resource.php");
        $this->line('  Add the columns to database/migrations/'.basename($migration));
        $this->line("  Register the morph alias in the module's service provider:");
        $this->line("      Relation::morphMap(['{$alias}' => {$name}::class]);");
        $this->line('  Then: php artisan migrate && php artisan recordset:check');

        return self::SUCCESS;
    }

    protected function resource(string $resourceNamespace, string $namespace, string $name): string
    {
        return <<<PHP
        <?php

        namespace {$resourceNamespace};

        use {$namespace}\\Models\\{$name};
        use BackedEnum;
        use Epesi\\Modules\\RecordBrowser\\Recordset\\Field;
        use Epesi\\Modules\\RecordBrowser\\Recordset\\RecordsetResource;
        use Filament\\Support\\Icons\\Heroicon;

        class {$name}Resource extends RecordsetResource
        {
            protected static ?string \$model = {$name}::class;

            protected static string|BackedEnum|null \$navigationIcon = Heroicon::OutlinedRectangleStack;

            protected static ?string \$recordTitleAttribute = 'name';

            /**
             * The whole recordset. List, View, Create, Edit, filters, search,
             * the History addon and any field an administrator adds are built
             * from this list — see RecordsetResource.
             */
            public static function fields(): array
            {
                return [
                    Field::text('name')->required()->inTable(),
                ];
            }
        }

        PHP;
    }

    /**
     * @return array<string, callable(string, string): string>
     */
    protected function pages(string $name, string $plural): array
    {
        $stub = fn (string $base): callable => function (string $resourceNamespace, string $name) use ($base, $plural): string {
            $class = str_replace(['{name}', '{plural}'], [$name, $plural], $base);

            return <<<PHP
            <?php

            namespace {$resourceNamespace}\\Pages;

            use {$resourceNamespace}\\{$name}Resource;
            use Epesi\\Modules\\RecordBrowser\\Recordset\\Pages\\{$this->baseFor($base)};

            /** Generated stub — all behaviour lives in the engine. */
            class {$class} extends {$this->baseFor($base)}
            {
                protected static string \$resource = {$name}Resource::class;
            }

            PHP;
        };

        return [
            "List{$plural}" => $stub('List{plural}'),
            "Create{$name}" => $stub('Create{name}'),
            "View{$name}" => $stub('View{name}'),
            "Edit{$name}" => $stub('Edit{name}'),
        ];
    }

    protected function baseFor(string $base): string
    {
        return match (true) {
            str_starts_with($base, 'List') => 'ListRecordset',
            str_starts_with($base, 'Create') => 'CreateRecordset',
            str_starts_with($base, 'View') => 'ViewRecordset',
            default => 'EditRecordset',
        };
    }

    protected function model(string $namespace, string $name, string $table): string
    {
        return <<<PHP
        <?php

        namespace {$namespace}\\Models;

        use Epesi\\Modules\\RecordBrowser\\Models\\Concerns\\HasCustomFields;
        use Illuminate\\Database\\Eloquent\\Model;
        use Spatie\\Activitylog\\LogOptions;
        use Spatie\\Activitylog\\Traits\\LogsActivity;

        class {$name} extends Model
        {
            use HasCustomFields, LogsActivity;

            protected \$table = '{$table}';

            protected \$fillable = [
                'name',
            ];

            public function getActivitylogOptions(): LogOptions
            {
                return LogOptions::defaults()
                    ->logOnlyDirty()
                    ->logFillable()
                    ->useLogName('{$table}');
            }
        }

        PHP;
    }

    protected function migration(string $table): string
    {
        return <<<PHP
        <?php

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', function (Blueprint \$table) {
                    \$table->id();
                    \$table->string('name');
                    \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };

        PHP;
    }
}
