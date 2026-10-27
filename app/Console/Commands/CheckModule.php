<?php

namespace App\Console\Commands;

use App\Support\Modules\ModuleManifest;
use Epesi\Modules\RecordBrowser\Console\RecordsetCheckCommand;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Validates a module package on disk, optionally against its installed schema and routes. */
class CheckModule extends RecordsetCheckCommand
{
    protected $signature = 'module:check {path? : Module path under modules/, e.g. Acme/Invoices} {--schema : Check installed recordsets, routes, and MySQL identifiers} {--strict : Report database columns not displayed by fields()}';

    protected $description = 'Check module package metadata and, optionally, installed recordsets and routes';

    /** @var array<string, ModuleManifest> */
    private array $manifestsById = [];

    private int $problems = 0;

    private int $warnings = 0;

    /** @var array<string, string> */
    private array $recordsetAliases = [];

    /** @var array<string, array<string, true>> */
    private array $aliasesByModule = [];

    public function handle(): int
    {
        $root = rtrim((string) config('modules.path'), '/\\');
        $targetPath = $this->argument('path');
        if ($targetPath !== null && ! preg_match('/^[A-Za-z][A-Za-z0-9]*(\/[A-Za-z][A-Za-z0-9]*)+$/', $targetPath)) {
            $this->components->error('Give a module path such as Acme/Invoices.');

            return self::FAILURE;
        }

        foreach (File::allFiles($root) as $file) {
            if ($file->getFilename() !== 'module.json') {
                continue;
            }
            try {
                $manifest = ModuleManifest::fromJson(File::get($file->getPathname()));
                $this->manifestsById[$manifest->id] = $manifest;
                $registration = dirname($file->getPathname()).DIRECTORY_SEPARATOR.'recordsets.json';
                if (is_file($registration)) {
                    foreach (json_decode(File::get($registration), true) ?: [] as $record) {
                        if (is_string($record['alias'] ?? null)) {
                            $this->aliasesByModule[$record['alias']][$manifest->id] = true;
                        }
                    }
                }
            } catch (Throwable) {
                // The selected module reports its invalid manifest below.
            }
        }

        $targets = $targetPath !== null
            ? [$targetPath]
            : array_map(fn (ModuleManifest $manifest): string => $manifest->path, $this->manifestsById);
        if ($targets === []) {
            $this->components->warn('No valid module manifests found.');

            return self::SUCCESS;
        }

        foreach ($targets as $path) {
            $directory = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
            $manifestPath = $directory.DIRECTORY_SEPARATOR.'module.json';
            try {
                $manifest = ModuleManifest::fromJson(File::get($manifestPath));
                if ($manifest->path !== $path) {
                    throw new \RuntimeException("Manifest declares path {$manifest->path}, but is located at {$path}.");
                }
                $this->checkPackage($manifest, $directory);
                if ($this->option('schema')) {
                    $this->checkInstalled($manifest);
                }
            } catch (Throwable $exception) {
                $this->reportProblem($path, $exception->getMessage());
            }
        }

        if ($this->problems > 0) {
            $this->components->error("{$this->problems} problem(s), {$this->warnings} warning(s).");

            return self::FAILURE;
        }
        $this->components->info(count($targets)." module(s) checked, no problems found ({$this->warnings} warning(s)).");

        return self::SUCCESS;
    }

    private function checkPackage(ModuleManifest $manifest, string $directory): void
    {
        $id = $manifest->id;
        foreach ($manifest->requires as $dependency) {
            if (! isset($this->manifestsById[$dependency])) {
                $this->reportProblem($id, "required module {$dependency} is not present under modules/");
            }
        }

        $slug = $manifest->migrationSlug();
        $migrationsPath = $directory.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        foreach (is_dir($migrationsPath) ? File::allFiles($migrationsPath) : [] as $migration) {
            if (! $manifest->core && ! str_contains($migration->getFilename(), $slug)) {
                $this->reportProblem($id, "migration {$migration->getFilename()} must contain module slug {$slug}");
            }
            $source = File::get($migration->getPathname());
            try {
                token_get_all($source, TOKEN_PARSE);
            } catch (Throwable $exception) {
                $this->reportProblem($id, "migration {$migration->getFilename()} has invalid PHP: {$exception->getMessage()}");
            }
            if (preg_match('/(?:Schema::(?:create|table)\s*\(\s*[\'"])([a-zA-Z0-9_]+)/', $source, $table)
                && strlen($table[1]) > 64) {
                $this->reportProblem($id, "table name {$table[1]} exceeds MySQL's 64-character identifier limit");
            }
        }

        $registrationPath = $directory.DIRECTORY_SEPARATOR.'recordsets.json';
        if (! is_file($registrationPath)) {
            $this->reportWarning($id, 'no recordsets.json: static checks cannot confirm aliases and policies registered manually by the provider');

            return;
        }
        try {
            $records = json_decode(File::get($registrationPath), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($records) || ! array_is_list($records)) {
                throw new \RuntimeException('recordsets.json must be a JSON list.');
            }
        } catch (Throwable $exception) {
            $this->reportProblem($id, 'recordsets.json: '.$exception->getMessage());

            return;
        }

        if ($records !== [] && ! in_array('epesi/recordbrowser', $manifest->requires, true)) {
            $this->reportProblem($id, 'recordsets.json requires epesi/recordbrowser in module.json');
        }
        foreach ($records as $index => $record) {
            $label = "recordsets.json entry {$index}";
            if (! is_array($record) || ! is_string($record['alias'] ?? null)
                || ! preg_match('/^[a-z][a-z0-9_]*$/', $record['alias'])) {
                $this->reportProblem($id, "{$label} needs a lowercase morph alias");

                continue;
            }
            $alias = $record['alias'];
            if (isset($this->recordsetAliases[$alias])) {
                $this->reportProblem($id, "morph alias {$alias} is also declared by {$this->recordsetAliases[$alias]}");
            }
            foreach (array_keys($this->aliasesByModule[$alias] ?? []) as $otherModule) {
                if ($otherModule !== $id) {
                    $this->reportProblem($id, "morph alias {$alias} is also declared by {$otherModule}");
                }
            }
            $this->recordsetAliases[$alias] = $id;
            foreach (['model', 'policy', 'resource'] as $classField) {
                $class = $record[$classField] ?? null;
                if (! is_string($class) || ! str_starts_with($class, $manifest->namespace)) {
                    $this->reportProblem($id, "{$label} {$classField} must be inside {$manifest->namespace}");

                    continue;
                }
                $file = $directory.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR
                    .str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($manifest->namespace))).'.php';
                if (! is_file($file)) {
                    $this->reportProblem($id, "{$label} {$classField} class file is missing: {$file}");
                }
            }
        }

        foreach (File::allFiles($directory) as $sourceFile) {
            if ($sourceFile->getExtension() !== 'php') {
                continue;
            }
            try {
                token_get_all(File::get($sourceFile->getPathname()), TOKEN_PARSE);
            } catch (Throwable $exception) {
                $this->reportProblem($id, "{$sourceFile->getRelativePathname()} has invalid PHP: {$exception->getMessage()}");
            }
        }
        if (is_dir($directory.DIRECTORY_SEPARATOR.'tests')) {
            $this->reportWarning($id, 'module tests are outside tests/Unit and tests/Feature; run php artisan test modules/'.$manifest->path.'/tests explicitly');
        }
    }

    private function checkInstalled(ModuleManifest $manifest): void
    {
        $recordsPath = config('modules.path').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $manifest->path).DIRECTORY_SEPARATOR.'recordsets.json';
        if (! is_file($recordsPath)) {
            $this->reportWarning($manifest->id, 'schema and route checks cover only registered RecordsetResource classes; no generated metadata is available');
        }
        foreach ($this->recordsetResources() as $resource) {
            if (! str_starts_with($resource, $manifest->namespace)) {
                continue;
            }
            $this->problems += $this->checkResource($resource);
        }

        foreach (json_decode(is_file($recordsPath) ? File::get($recordsPath) : '[]', true) ?: [] as $record) {
            foreach (['model' => 'morph alias', 'policy' => 'policy'] as $field => $label) {
                if (! is_string($record[$field] ?? null) || ! class_exists($record[$field])) {
                    $this->reportProblem($manifest->id, "{$label} target is missing for {$record['alias']}");

                    continue;
                }
            }
            if (class_exists($record['model'] ?? '')) {
                if (Relation::getMorphAlias($record['model']) === ($record['alias'] ?? null)) {
                    // Registered morph aliases resolve back to their class.
                } else {
                    $this->reportProblem($manifest->id, "morph alias {$record['alias']} is not registered for {$record['model']}");
                }
                if (Gate::getPolicyFor($record['model']) !== ($record['policy'] ?? null)) {
                    $this->reportProblem($manifest->id, "policy {$record['policy']} is not registered for {$record['model']}");
                }
            }
        }

        $this->checkRouteOverlaps($manifest);
        $this->checkMysqlIdentifiers($manifest);
    }

    private function checkRouteOverlaps(ModuleManifest $manifest): void
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            $uses = $route->getAction('uses');
            $class = is_string($uses) ? strtok($uses, '@') : (is_array($uses) && isset($uses[0]) && is_object($uses[0]) ? $uses[0]::class : null);
            if (! is_string($class)) {
                continue;
            }
            $owner = 'app';
            foreach ($this->manifestsById as $candidate) {
                if (str_starts_with($class, $candidate->namespace)) {
                    $owner = $candidate->id;
                    break;
                }
            }
            $routes[] = ['uri' => $route->uri(), 'methods' => $route->methods(), 'owner' => $owner, 'name' => $route->getName()];
        }
        foreach ($routes as $index => $left) {
            foreach (array_slice($routes, $index + 1) as $right) {
                if ($left['owner'] === $right['owner'] || ($left['owner'] !== $manifest->id && $right['owner'] !== $manifest->id)) {
                    continue;
                }
                if (array_intersect($left['methods'], $right['methods']) === []) {
                    continue;
                }
                if ($this->routeShape($left['uri']) === $this->routeShape($right['uri'])) {
                    $this->reportProblem($manifest->id, "route {$left['uri']} ({$left['owner']}) overlaps {$right['uri']} ({$right['owner']}); module:check --schema inspects routes registered in the current process");
                } elseif ($this->routeMayOverlap($left['uri'], $right['uri'])) {
                    $this->reportWarning($manifest->id, "route {$left['uri']} may overlap {$right['uri']}; check variable constraints and route order");
                }
            }
        }
    }

    private function routeShape(string $uri): string
    {
        return preg_replace('/\{[^}]+\}/', '{}', trim($uri, '/')) ?? $uri;
    }

    private function routeMayOverlap(string $left, string $right): bool
    {
        $a = explode('/', trim($left, '/'));
        $b = explode('/', trim($right, '/'));
        if (count($a) !== count($b)) {
            return false;
        }
        foreach ($a as $index => $part) {
            if ($part !== $b[$index] && ! (str_starts_with($part, '{') || str_starts_with($b[$index], '{'))) {
                return false;
            }
        }

        return true;
    }

    private function checkMysqlIdentifiers(ModuleManifest $manifest): void
    {
        foreach (Schema::getTableListing() as $table) {
            if (! is_string($table) || ! str_starts_with($table, $manifest->migrationSlug().'_')) {
                continue;
            }
            foreach (Schema::getIndexes($table) as $index) {
                if (strlen($index['name']) > 64) {
                    $this->reportProblem($manifest->id, "index {$index['name']} on {$table} exceeds MySQL's 64-character limit");
                }
            }
            if (config('database.default') === 'mysql') {
                $keys = DB::select('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
                foreach ($keys as $key) {
                    if (strlen($key->CONSTRAINT_NAME) > 64) {
                        $this->reportProblem($manifest->id, "foreign key {$key->CONSTRAINT_NAME} on {$table} exceeds MySQL's 64-character limit");
                    }
                }
            } else {
                $this->reportWarning($manifest->id, "foreign-key names on {$table} are only available when the schema check runs against MySQL/MariaDB");
            }
        }
    }

    private function reportProblem(string $module, string $message): void
    {
        $this->components->twoColumnDetail($module, '<fg=red>'.$message.'</>');
        $this->problems++;
    }

    private function reportWarning(string $module, string $message): void
    {
        $this->components->twoColumnDetail($module, '<fg=yellow>'.$message.'</>');
        $this->warnings++;
    }
}
