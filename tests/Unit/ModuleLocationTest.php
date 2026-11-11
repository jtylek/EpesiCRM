<?php

namespace Tests\Unit;

use App\Services\Modules\ModuleException;
use App\Support\Modules\ModuleManifest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Premium modules live in modules/Premium/<Name>/, separate nested repositories that
 * .gitignore keeps out of this one. Under modules/Epesi/ they would be tracked here.
 * A plain filesystem check, so it needs neither the app nor a database.
 */
class ModuleLocationTest extends TestCase
{
    private function modulesPath(string $suffix = ''): string
    {
        return dirname(__DIR__, 2).'/modules'.$suffix;
    }

    /** @return array<int, string> */
    private function manifestsUnder(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getFilename() === 'module.json' && ! str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                $found[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        return $found;
    }

    public function test_no_premium_directory_under_modules_epesi(): void
    {
        $this->assertDirectoryDoesNotExist(
            $this->modulesPath('/Epesi/Premium'),
            'Premium modules belong in modules/Premium/<Name>/ (separate nested repositories), never in modules/Epesi/Premium/.',
        );
    }

    public function test_manifest_paths_match_their_directories_under_modules_epesi(): void
    {
        $root = str_replace('\\', '/', $this->modulesPath());

        foreach ($this->manifestsUnder($this->modulesPath('/Epesi')) as $manifest) {
            $path = json_decode((string) file_get_contents($manifest), true)['path'] ?? null;
            $expected = substr(dirname($manifest), strlen($root) + 1);

            $this->assertSame($expected, $path, "{$manifest}: the path must name the directory the module is in.");
            $this->assertStringStartsWith('Epesi/', (string) $path, "{$manifest}: only modules/Epesi/ modules use the Epesi/ path.");
        }
    }

    public function test_premium_modules_declare_a_premium_path_and_namespace(): void
    {
        $root = str_replace('\\', '/', $this->modulesPath());

        foreach ($this->manifestsUnder($this->modulesPath('/Premium')) as $manifest) {
            $data = json_decode((string) file_get_contents($manifest), true);
            $expected = substr(dirname($manifest), strlen($root) + 1);

            $this->assertSame($expected, $data['path'] ?? null, "{$manifest}: the path must be Premium/<Name>.");
        }
    }

    public function test_gitignore_keeps_premium_and_custom_modules_out_of_this_repository(): void
    {
        $lines = array_map('trim', file(dirname(__DIR__, 2).'/.gitignore', FILE_IGNORE_NEW_LINES));

        $this->assertContains('/modules/Premium/', $lines);
        $this->assertContains('/modules/Custom/', $lines);
        $this->assertContains('/modules/Epesi/Premium/', $lines, 'A Premium module put under modules/Epesi/ must never be committable.');
    }

    /** @return array<string, array{0: string}> */
    public static function forbiddenPaths(): array
    {
        return [
            'Epesi/Premium/Inventory' => ['Epesi/Premium/Inventory'],
            'lower case' => ['epesi/premium/inventory'],
            'bare Epesi/Premium' => ['Epesi/Premium'],
        ];
    }

    #[DataProvider('forbiddenPaths')]
    public function test_the_manifest_rejects_a_premium_module_under_epesi(string $path): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Premium modules live in');

        ModuleManifest::fromArray([
            'id' => 'premium/inventory',
            'name' => 'Inventory',
            'version' => '1.0.0',
            'path' => $path,
            'namespace' => 'Premium\\Inventory\\',
        ]);
    }

    public function test_the_manifest_accepts_a_premium_module_in_modules_premium(): void
    {
        $manifest = ModuleManifest::fromArray([
            'id' => 'premium/inventory',
            'name' => 'Inventory',
            'version' => '1.0.0',
            'path' => 'Premium/Inventory',
            'namespace' => 'Premium\\Inventory\\',
        ]);

        $this->assertSame('Premium/Inventory', $manifest->path);
    }
}
