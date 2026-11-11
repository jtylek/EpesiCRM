<?php

namespace Tests\Feature;

use App\Support\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A module renamed in place keeps its old `modules` row until the update
 * migration rewrites it. Booting must use the manifest on disk meanwhile, or the
 * old and new namespaces both reach the same src/ and the class is declared
 * twice, so epesi:update could never run.
 */
class ModuleRegistryRenamedModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $modules;

    protected string $originalModules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalModules = config('modules.path');
        $this->modules = storage_path('framework/testing/modules-'.uniqid());
        config(['modules.path' => $this->modules]);

        File::ensureDirectoryExists($this->modules.'/Premium/Widgets');
        File::put($this->modules.'/Premium/Widgets/module.json', json_encode([
            'id' => 'premium/widgets',
            'name' => 'Widgets',
            'version' => '1.0.0',
            'epesi_core' => '^3.0',
            'namespace' => 'Premium\\Widgets\\',
            'path' => 'Premium/Widgets',
            'provider_class' => 'Premium\\Widgets\\WidgetsServiceProvider',
            'plugin_class' => 'Premium\\Widgets\\WidgetsPlugin',
            'panels' => ['main'],
            'requires' => [],
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modules);
        config(['modules.path' => $this->originalModules]);

        parent::tearDown();
    }

    public function test_the_manifest_wins_over_a_stored_row_with_the_old_namespace(): void
    {
        $this->insertRow('epesi/widgets', 'Epesi\\Modules\\Widgets\\');

        $module = $this->readDatabase()[0];

        $this->assertSame('premium/widgets', $module['id']);
        $this->assertSame('Premium\\Widgets\\', $module['namespace']);
        $this->assertSame('Premium\\Widgets\\WidgetsServiceProvider', $module['provider']);
        $this->assertSame('Premium\\Widgets\\WidgetsPlugin', $module['plugin']);
        $this->assertSame(['main'], $module['panels']);
    }

    public function test_a_row_that_matches_its_manifest_is_used_as_stored(): void
    {
        $this->insertRow('premium/widgets', 'Premium\\Widgets\\');

        $module = $this->readDatabase()[0];

        $this->assertSame('premium/widgets', $module['id']);
        $this->assertSame('Acme\\Stored\\Provider', $module['provider']);
    }

    public function test_a_row_without_a_manifest_on_disk_is_used_as_stored(): void
    {
        $this->insertRow('epesi/gone', 'Epesi\\Modules\\Gone\\', 'Epesi/Gone');

        $this->assertSame('Epesi\\Modules\\Gone\\', $this->readDatabase()[0]['namespace']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function readDatabase(): array
    {
        $registry = new class extends ModuleRegistry
        {
            public static function read(): array
            {
                return self::readDatabase() ?? [];
            }
        };

        return $registry::read();
    }

    protected function insertRow(string $id, string $namespace, string $path = 'Premium/Widgets'): void
    {
        DB::table('modules')->insert([
            'module_id' => $id,
            'name' => 'Widgets',
            'version' => '1.0.0',
            'path' => $path,
            'namespace' => $namespace,
            'provider_class' => 'Acme\\Stored\\Provider',
            'plugin_class' => null,
            'panels' => json_encode([]),
            'manifest' => json_encode([]),
            'enabled' => true,
        ]);
    }
}
