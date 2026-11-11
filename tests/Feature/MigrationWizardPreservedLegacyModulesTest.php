<?php

namespace Tests\Feature;

use App\Services\Modules\ModuleDiscovery;
use FilesystemIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * A migration preserves legacy Premium/Custom module code where it is. Such a
 * directory (legacy modules have no module.json) must never be discovered as an
 * Epesi 3 module, and its PHP is never included.
 */
class MigrationWizardPreservedLegacyModulesTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/modules-'.bin2hex(random_bytes(6)));
        mkdir($this->root.'/Premium/Legacy/Sub', 0775, true);
        mkdir($this->root.'/Custom/Odd', 0775, true);
        $marker = var_export($this->root.'/executed', true);
        // Would leave a trace if anything ever included it.
        file_put_contents($this->root.'/Premium/Legacy/LegacyCommon_0.php', '<?php file_put_contents('.$marker.", 'x'); class Premium_Legacy {}");
        file_put_contents($this->root.'/Premium/Legacy/Sub/Foo.php', '<?php file_put_contents('.$marker.", 'x');");
        file_put_contents($this->root.'/Custom/Odd/module.json', '{not json');
        config(['modules.path' => $this->root]);
    }

    protected function tearDown(): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            ($entry->isDir() && ! $entry->isLink()) ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->root);
        parent::tearDown();
    }

    public function test_legacy_directories_without_a_valid_manifest_are_not_modules_and_are_never_executed(): void
    {
        $found = (new ModuleDiscovery)->scan();

        // Only the directory that has a (broken) module.json shows up, and as invalid; the legacy code does not.
        self::assertSame(['Custom/Odd'], array_column($found, 'path'));
        self::assertSame(ModuleDiscovery::INVALID, $found[0]['status']);
        self::assertFileDoesNotExist($this->root.'/executed');
        self::assertFileExists($this->root.'/Premium/Legacy/LegacyCommon_0.php', 'discovery never touches the preserved files');
        self::assertFalse(class_exists('Premium_Legacy', false));
    }
}
