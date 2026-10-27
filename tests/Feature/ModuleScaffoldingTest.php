<?php

namespace Tests\Feature;

use Composer\Autoload\ClassLoader;
use Fixture\Modules\Scaffold\ScaffoldServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class ModuleScaffoldingTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    private string $scratch;

    private string $moduleRoot;

    private array $morphMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moduleRoot = config('modules.path');
        $this->scratch = storage_path('framework/testing/scaffold-'.uniqid());
        File::ensureDirectoryExists($this->scratch);
        $this->morphMap = Relation::morphMap();
    }

    protected function tearDown(): void
    {
        Relation::morphMap($this->morphMap, merge: false);
        File::deleteDirectory($this->scratch);
        parent::tearDown();
    }

    private function scaffold(string $name, bool $ownership = false): string
    {
        config(['modules.path' => $this->scratch]);
        $this->artisan('make:epesi-module', ['name' => 'Fixture/Scaffold'])->assertSuccessful();
        // No module row exists; the generator must work from files alone.
        $this->assertDatabaseMissing('modules', ['path' => 'Fixture/Scaffold']);
        $this->artisan('make:epesi-recordset', ['module' => 'Fixture/Scaffold', 'name' => $name, '--ownership' => $ownership])->assertSuccessful();
        config(['modules.path' => $this->moduleRoot]);

        return $this->scratch.'/Fixture/Scaffold';
    }

    public function test_generated_recordset_has_working_authors_history_policy_and_migration_without_ownership(): void
    {
        $directory = $this->scaffold('PublicEntry');
        $loader = new ClassLoader;
        $loader->addPsr4('Fixture\\Modules\\Scaffold\\', $directory.'/src');
        $loader->register();
        try {
            foreach (File::allFiles($directory) as $file) {
                if ($file->getExtension() === 'php') {
                    token_get_all(File::get($file->getPathname()), TOKEN_PARSE);
                }
            }
            $provider = new ScaffoldServiceProvider($this->app);
            $provider->register();
            $provider->boot();
            foreach (File::glob($directory.'/database/migrations/*.php') as $file) {
                (require $file)->up();
            }
            $user = $this->userWithRole('employee');
            $this->actingAs($user);
            $class = 'Fixture\\Modules\\Scaffold\\Models\\PublicEntry';
            $entry = $class::create(['name' => 'First']);
            $this->assertSame($user->id, $entry->creator->id);
            $entry->update(['name' => 'Changed']);
            $this->assertSame($user->id, $entry->lastUpdater()->id);
            $this->assertSame('fixture_scaffold_public_entry', $entry->getMorphClass());
            $this->assertTrue(Gate::allows('update', $entry));
            $this->actingAs($this->userWithRole('employee'));
            $this->assertTrue(Gate::allows('view', $entry));
            $this->assertFalse(Gate::allows('update', $entry));
            $this->assertFalse(Schema::hasColumn($entry->getTable(), 'permission'));
            $manifest = json_decode(File::get($directory.'/module.json'), true);
            $this->assertContains('epesi/recordbrowser', $manifest['requires']);
        } finally {
            $loader->unregister();
        }
    }

    public function test_ownership_generation_and_duplicate_refusal_preserve_files(): void
    {
        $directory = $this->scaffold('PrivateEntry', ownership: true);
        $model = File::get($directory.'/src/Models/PrivateEntry.php');
        $this->assertStringContainsString('HasOwnershipVisibility', $model);
        $this->assertStringContainsString('RecordPermission::class', $model);
        $files = collect(File::allFiles($directory))->mapWithKeys(fn ($file) => [$file->getPathname() => hash_file('sha256', $file->getPathname())])->all();
        config(['modules.path' => $this->scratch]);
        $this->artisan('make:epesi-recordset', ['module' => 'Fixture/Scaffold', 'name' => 'PrivateEntry'])->assertFailed();
        foreach ($files as $file => $hash) {
            $this->assertSame($hash, hash_file('sha256', $file));
        }
    }

    public function test_custom_provider_and_unsafe_paths_are_rejected_before_writing(): void
    {
        config(['modules.path' => $this->scratch]);
        $this->artisan('make:epesi-module', ['name' => 'Fixture/Custom'])->assertSuccessful();
        $provider = $this->scratch.'/Fixture/Custom/src/CustomServiceProvider.php';
        File::put($provider, '<?php // A manually maintained provider.');
        $this->artisan('make:epesi-recordset', ['module' => 'Fixture/Custom', 'name' => 'Invoice'])->assertFailed();
        $this->assertDirectoryDoesNotExist($this->scratch.'/Fixture/Custom/src/Models');
        $this->assertFileDoesNotExist($this->scratch.'/Fixture/Custom/recordsets.json');
        $this->artisan('make:epesi-recordset', ['module' => '../Outside', 'name' => 'Invoice'])->assertFailed();
    }

    public function test_reserved_php_name_is_rejected_before_writing(): void
    {
        config(['modules.path' => $this->scratch]);
        $this->artisan('make:epesi-module', ['name' => 'Fixture/Reserved'])->assertSuccessful();
        $this->artisan('make:epesi-recordset', ['module' => 'Fixture/Reserved', 'name' => 'Class'])->assertFailed();
        $this->assertFileDoesNotExist($this->scratch.'/Fixture/Reserved/recordsets.json');
    }

    public function test_module_check_validates_a_generated_package_before_registration(): void
    {
        $directory = $this->scaffold('ExampleEntry');
        $coreManifest = base_path('modules/Epesi/RecordBrowser/module.json');
        File::ensureDirectoryExists($this->scratch.'/Epesi/RecordBrowser');
        File::copy($coreManifest, $this->scratch.'/Epesi/RecordBrowser/module.json');
        config(['modules.path' => $this->scratch]);
        $exitCode = Artisan::call('module:check', ['path' => 'Fixture/Scaffold']);
        $this->assertSame(0, $exitCode, Artisan::output());
    }

    public function test_module_check_reports_missing_dependencies_and_duplicate_aliases(): void
    {
        $directory = $this->scaffold('OneEntry');
        $manifestPath = $directory.'/module.json';
        $manifest = json_decode(File::get($manifestPath), true);
        $manifest['requires'][] = 'missing/example';
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));

        $other = $this->scratch.'/Fixture/Other';
        File::ensureDirectoryExists($other.'/src');
        File::put($other.'/module.json', json_encode([
            'id' => 'fixture/other', 'name' => 'Other', 'version' => '1.0.0',
            'path' => 'Fixture/Other', 'namespace' => 'Fixture\\Modules\\Other\\',
        ]));
        File::put($other.'/recordsets.json', json_encode([['alias' => 'fixture_scaffold_one_entry',
            'model' => 'Fixture\\Modules\\Other\\Models\\Other',
            'policy' => 'Fixture\\Modules\\Other\\Policies\\OtherPolicy',
            'resource' => 'Fixture\\Modules\\Other\\Filament\\Resources\\Others\\OtherResource']]));

        config(['modules.path' => $this->scratch]);
        $this->artisan('module:check', ['path' => 'Fixture/Scaffold'])->assertFailed();
    }
}
