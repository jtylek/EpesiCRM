<?php

namespace App\Console\Commands;

use App\Models\Module;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Scaffolds a module in place under modules/, ready to develop against and to
 * release with `module:package`. Deliberately minimal: manifest, service
 * provider, Filament plugin. modules/Epesi/Notes is the worked example of what
 * to add next (models, migrations, Filament resources).
 */
class MakeEpesiModule extends Command
{
    protected $signature = 'make:epesi-module {name : Vendor/Name, e.g. Acme/Invoices (nesting allowed: Epesi/CRM/Contacts)}';

    protected $description = 'Scaffold a new Epesi module directory';

    public function handle(): int
    {
        $parts = array_map(
            fn (string $part): string => Str::studly($part),
            explode('/', str_replace('\\', '/', (string) $this->argument('name'))),
        );

        if (count($parts) < 2 || in_array('', $parts, true)) {
            $this->components->error('Give the module as Vendor/Name, e.g. Acme/Invoices (nesting allowed: Epesi/CRM/Contacts).');

            return self::FAILURE;
        }

        $vendor = $parts[0];
        $name = end($parts);
        $path = implode('/', $parts);

        // The namespace mirrors the path with "Modules" inserted after the
        // vendor, so Epesi/CRM/Contacts is Epesi\Modules\CRM\Contacts\. Composer
        // matches the longest registered PSR-4 prefix first, so a parent
        // module's mapping can never shadow a nested child's.
        $namespace = implode('\\', [$vendor, 'Modules', ...array_slice($parts, 1)]);
        $directory = Module::directoryFor($path);

        if (is_dir($directory)) {
            $this->components->error("modules/{$path} already exists.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists($directory.'/src');
        File::ensureDirectoryExists($directory.'/database/migrations');
        File::ensureDirectoryExists($directory.'/src/Filament/Resources');

        $id = $this->moduleId($parts);

        File::put($directory.'/module.json', $this->manifest($id, $name, $path, $namespace));
        File::put($directory."/src/{$name}ServiceProvider.php", $this->serviceProvider($name, $namespace));
        File::put($directory."/src/{$name}Plugin.php", $this->plugin($id, $name, $namespace));

        $this->components->info("Created modules/{$path}");
        $this->line('  Add resources under src/Filament/Resources, migrations under database/migrations');
        $this->line('  (migration filenames must contain "'.str_replace(['/', '-', '.'], '_', $id).'").');
        $this->line("  Then: php artisan module:package {$path}");

        return self::SUCCESS;
    }

    /**
     * Module ids stay two-segment whatever the path depth — Epesi/CRM/Contacts
     * is `epesi/crm-contacts` — because ModuleManifest's id regex allows exactly
     * `vendor/name`, and `requires:` entries and the migration slug both key off
     * the id.
     *
     * @param  array<int, string>  $parts
     */
    protected function moduleId(array $parts): string
    {
        $slug = fn (string $part): string => ctype_upper($part) ? strtolower($part) : Str::kebab($part);

        return $slug($parts[0]).'/'.implode('-', array_map($slug, array_slice($parts, 1)));
    }

    protected function manifest(string $id, string $name, string $path, string $namespace): string
    {
        return json_encode([
            'id' => $id,
            'name' => Str::headline($name),
            'description' => '',
            'version' => '1.0.0',
            'epesi_core' => '^'.config('modules.core_version'),
            'category' => '',
            'icon' => 'heroicon-o-puzzle-piece',
            'namespace' => $namespace.'\\',
            'path' => $path,
            'provider_class' => $namespace.'\\'.$name.'ServiceProvider',
            'plugin_class' => $namespace.'\\'.$name.'Plugin',
            'panels' => ['main'],
            'requires' => [],
            'provides' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    protected function serviceProvider(string $name, string $namespace): string
    {
        return <<<PHP
        <?php

        namespace {$namespace};

        use Illuminate\Support\ServiceProvider;

        class {$name}ServiceProvider extends ServiceProvider
        {
            public function boot(): void
            {
                \$this->loadMigrationsFrom(__DIR__.'/../database/migrations');
            }
        }

        PHP;
    }

    protected function plugin(string $moduleId, string $name, string $namespace): string
    {
        $id = str_replace('/', '-', $moduleId);
        $resourceNamespace = str_replace('\\', '\\\\', $namespace).'\\\\Filament\\\\Resources';

        return <<<PHP
        <?php

        namespace {$namespace};

        use Filament\Contracts\Plugin;
        use Filament\Panel;

        class {$name}Plugin implements Plugin
        {
            public static function make(): static
            {
                return app(static::class);
            }

            public function getId(): string
            {
                return '{$id}';
            }

            public function register(Panel \$panel): void
            {
                \$panel->discoverResources(
                    in: __DIR__.'/Filament/Resources',
                    for: '{$resourceNamespace}',
                );
            }

            public function boot(Panel \$panel): void {}
        }

        PHP;
    }
}
