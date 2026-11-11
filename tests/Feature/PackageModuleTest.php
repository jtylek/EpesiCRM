<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/**
 * A module nested inside another (Premium/Equipment/Service) is a separate
 * product: the parent's zip must not carry it, and its own zip must.
 */
class PackageModuleTest extends TestCase
{
    protected string $modules;

    protected string $out;

    protected string $originalModules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalModules = config('modules.path');
        $this->modules = storage_path('framework/testing/modules-'.uniqid());
        $this->out = $this->modules.'-out';
        config(['modules.path' => $this->modules]);

        $this->module('Premium/Equipment', 'premium/equipment', 'Premium\\Equipment\\');
        $this->module('Premium/Equipment/Service', 'premium/equipmentservice', 'Premium\\EquipmentService\\');
        File::put($this->modules.'/Premium/Equipment/Service/src/Job.php', '<?php');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modules);
        File::deleteDirectory($this->out);
        config(['modules.path' => $this->originalModules]);

        parent::tearDown();
    }

    public function test_the_parent_zip_leaves_out_a_nested_module(): void
    {
        $this->artisan('module:package', ['path' => 'Premium/Equipment', '--out' => $this->out])->assertSuccessful();

        $entries = $this->entries('premium-equipment-1.0.0.zip');

        $this->assertContains('Premium/Equipment/module.json', $entries);
        $this->assertContains('Premium/Equipment/src/Thing.php', $entries);
        $this->assertSame([], array_values(array_filter(
            $entries,
            fn (string $entry): bool => str_starts_with($entry, 'Premium/Equipment/Service/'),
        )));
    }

    public function test_the_nested_module_packages_on_its_own(): void
    {
        $this->artisan('module:package', ['path' => 'Premium/Equipment/Service', '--out' => $this->out])->assertSuccessful();

        $entries = $this->entries('premium-equipmentservice-1.0.0.zip');

        $this->assertContains('Premium/Equipment/Service/module.json', $entries);
        $this->assertContains('Premium/Equipment/Service/src/Job.php', $entries);
    }

    protected function module(string $path, string $id, string $namespace): void
    {
        File::ensureDirectoryExists($this->modules.'/'.$path.'/src');
        File::put($this->modules.'/'.$path.'/src/Thing.php', '<?php');
        File::put($this->modules.'/'.$path.'/module.json', json_encode([
            'id' => $id,
            'name' => $id,
            'version' => '1.0.0',
            'namespace' => $namespace,
            'path' => $path,
        ]));
    }

    /**
     * @return array<int, string>
     */
    protected function entries(string $zip): array
    {
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($this->out.'/'.$zip) === true);

        $entries = [];

        for ($i = 0; $i < $archive->numFiles; $i++) {
            $entries[] = $archive->getNameIndex($i);
        }

        $archive->close();

        return $entries;
    }
}
