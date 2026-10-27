<?php

namespace Epesi\Modules\RecordBrowser\Console;

use App\Models\Module;
use App\Support\Modules\ModuleManifest;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

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
        {module : the module to add it to, e.g. Epesi/Attachments}
        {name : singular model name, e.g. Invoice}
        {--ownership : Include CRM ownership visibility and its Permission field}';

    protected $description = 'Scaffold a recordset (model, migration, resource with fields(), page stubs) inside a module';

    public function handle(): int
    {
        $modulePath = trim(str_replace('\\', '/', (string) $this->argument('module')), '/');
        $name = Str::studly((string) $this->argument('name'));
        $plural = Str::plural($name);
        if (! preg_match('/^[A-Za-z][A-Za-z0-9]*(\/[A-Za-z][A-Za-z0-9]*)+$/', $modulePath)
            || ! preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name)) {
            $this->components->error('Use a Vendor/Module path and a simple singular PHP class name.');

            return self::FAILURE;
        }
        $directory = Module::directoryFor($modulePath);
        try {
            $manifest = ModuleManifest::fromJson(File::get($directory.'/module.json'));
            if ($manifest->path !== $modulePath || ! $manifest->providerClass || ! $manifest->pluginClass) {
                throw new \RuntimeException('The manifest must match the module path and declare a provider and plugin.');
            }
            $provider = $directory.'/src/'.str_replace('\\', '/', substr($manifest->providerClass, strlen($manifest->namespace))).'.php';
            $source = File::get($provider);
            if (! str_contains($source, '// epesi:recordsets-json')
                || ! str_contains($source, "Relation::morphMap([\$recordset['alias'] => \$recordset['model']])")
                || ! str_contains($source, "Gate::policy(\$recordset['model'], \$recordset['policy'])")) {
                throw new \RuntimeException('Provider has no generated recordsets.json hooks. Add the register()/boot() loops and recordsets() reader shown in AI-shared/AI-agent-module-development.md first. No files changed.');
            }
            $registrations = is_file($directory.'/recordsets.json')
                ? json_decode(File::get($directory.'/recordsets.json'), true, 512, JSON_THROW_ON_ERROR) : [];
            if (! is_array($registrations) || ! array_is_list($registrations)) {
                throw new \RuntimeException('recordsets.json must contain a list of registrations.');
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $namespace = rtrim($manifest->namespace, '\\');
        $resourceNamespace = "{$namespace}\\Filament\\Resources\\{$plural}";
        $resourceDirectory = "{$directory}/src/Filament/Resources/{$plural}";
        // Module tables carry the module's own slug so two modules can never
        // claim the same table name — and the migration filename inherits it,
        // which is what ModuleArchive requires of a distributable module.
        $slug = $manifest->migrationSlug();
        $table = $slug.'_'.Str::snake($plural);
        $alias = $slug.'_'.Str::snake($name);
        if (strlen($table) > 64 || strlen($table.'_created_by_index') > 64) {
            $this->components->error('Generated table/index names exceed MySQL\'s 64-character limit; use shorter module and recordset names.');

            return self::FAILURE;
        }
        if (is_dir($resourceDirectory) || Relation::getMorphedModel($alias)
            || in_array($alias, array_column($registrations, 'alias'), true)) {
            $this->components->error("{$plural} already exists in {$modulePath}.");

            return self::FAILURE;
        }

        $files = ["{$resourceDirectory}/{$name}Resource.php" => $this->resource($resourceNamespace, $namespace, $name)];

        foreach ($this->pages($name, $plural) as $file => $contents) {
            $files["{$resourceDirectory}/Pages/{$file}.php"] = $contents($resourceNamespace, $name);
        }

        $modelFile = "{$directory}/src/Models/{$name}.php";

        $files[$modelFile] = $this->model($namespace, $name, $table);
        $files["{$directory}/src/Policies/{$name}Policy.php"] = $this->policy($namespace, $name);

        $migration = "{$directory}/database/migrations/".now()->format('Y_m_d_His')
            .'_create_'.$table.'_table.php';

        $files[$migration] = $this->migration($table);
        try {
            foreach ($files as $path => $contents) {
                if (file_exists($path)) {
                    throw new \RuntimeException("Refusing to overwrite {$path}. No files changed.");
                }
                token_get_all($contents, TOKEN_PARSE);
            }
            if (File::glob("{$directory}/database/migrations/*_create_{$table}_table.php") !== []) {
                throw new \RuntimeException('A migration for this recordset already exists. No files changed.');
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $contents);
        }
        $registrations[] = ['alias' => $alias, 'model' => "{$namespace}\\Models\\{$name}",
            'policy' => "{$namespace}\\Policies\\{$name}Policy", 'resource' => "{$resourceNamespace}\\{$name}Resource"];
        File::replace($directory.'/recordsets.json', json_encode($registrations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        $data = $manifest->raw;
        $data['requires'] = array_values(array_unique([...$manifest->requires, 'epesi/recordbrowser']));
        File::replace($directory.'/module.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->components->info("Created {$plural} in modules/{$modulePath}");
        $this->line("  Declare the fields in src/Filament/Resources/{$plural}/{$name}Resource.php");
        $this->line('  Add the columns to database/migrations/'.basename($migration));
        $this->line("  Registered {$alias} and {$name}Policy in recordsets.json; added the RecordBrowser dependency.");
        $this->line("  Check: php artisan module:check {$modulePath}");
        $this->line("  Review SQL: php artisan migrate --pretend --path=modules/{$modulePath}/database/migrations");
        $this->line("  On your development installation: php artisan module:register {$modulePath}");
        $this->line("  Then: php artisan module:check {$modulePath} --schema");

        return self::SUCCESS;
    }

    protected function resource(string $resourceNamespace, string $namespace, string $name): string
    {
        $permission = $this->option('ownership')
            ? "\n                    Field::select('permission', \\App\\Enums\\RecordPermission::class)->required()->default(\\App\\Enums\\RecordPermission::Public)," : '';

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
                    Field::text('name')->required()->inTable(),{$permission}
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
        $authors = $this->option('ownership') ? 'HasOwnershipVisibility' : 'HasRecordAuthors';
        $permission = $this->option('ownership') ? "\n                'permission'," : '';
        $casts = $this->option('ownership') ? <<<'PHP'

            protected $attributes = ['permission' => 0];

            protected function casts(): array
            {
                return ['permission' => \App\Enums\RecordPermission::class];
            }

        PHP : '';

        return <<<PHP
        <?php

        namespace {$namespace}\\Models;

        use Epesi\\Modules\\RecordBrowser\\Models\\Concerns\\HasCustomFields;
        use Epesi\\Modules\\RecordBrowser\\Models\\Concerns\\{$authors};
        use Illuminate\\Database\\Eloquent\\Model;
        use Spatie\\Activitylog\\LogOptions;
        use Spatie\\Activitylog\\Traits\\LogsActivity;

        class {$name} extends Model
        {
            use HasCustomFields, {$authors}, LogsActivity;

            protected \$table = '{$table}';

            protected \$fillable = [
                'name',{$permission}
            ];
        {$casts}

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
        $permission = $this->option('ownership') ? "\n                    \$table->unsignedTinyInteger('permission')->default(0);" : '';

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
                    \$table->string('name');{$permission}
                    \$table->foreignId('created_by')->nullable()->index();
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

    protected function policy(string $namespace, string $name): string
    {
        return <<<PHP
        <?php

        namespace {$namespace}\\Policies;

        use App\\Models\\User;
        use {$namespace}\\Models\\{$name};

        /** Staff can read/create; creators and managers can change records. */
        class {$name}Policy
        {
            public function viewAny(User \$user): bool
            {
                return \$user->hasAnyRole(['super_admin', 'manager', 'employee']);
            }

            public function view(User \$user, {$name} \$record): bool
            {
                return \$this->viewAny(\$user) && \$record->newQuery()->whereKey(\$record->getKey())->exists();
            }

            public function create(User \$user): bool
            {
                return \$this->viewAny(\$user);
            }

            public function update(User \$user, {$name} \$record): bool
            {
                return \$this->view(\$user, \$record)
                    && (\$user->hasAnyRole(['super_admin', 'manager']) || \$record->created_by === \$user->id);
            }

            public function delete(User \$user, {$name} \$record): bool
            {
                return \$this->update(\$user, \$record);
            }

            public function restore(User \$user, {$name} \$record): bool
            {
                return \$this->viewAny(\$user)
                    && (\$user->hasAnyRole(['super_admin', 'manager']) || \$record->created_by === \$user->id);
            }

            public function forceDelete(User \$user, {$name} \$record): bool
            {
                return \$user->hasRole('super_admin');
            }
        }

        PHP;
    }
}
