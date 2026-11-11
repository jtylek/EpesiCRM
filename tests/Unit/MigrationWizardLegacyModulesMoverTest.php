<?php

use Epesi\Modules\MigrationWizard\Cleanup\LegacyModulesMover;
use PHPUnit\Framework\TestCase;

final class MigrationWizardLegacyModulesMoverTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/epesi-mover-'.bin2hex(random_bytes(5));
        $files = [
            'modules/Epesi/CRM/Contacts/module.json' => '{}',
            'modules/Premium/Equipment/module.json' => '{}',
            'modules/Premium/Equipment/Service/module.json' => '{}',
            'modules/Premium/ExchangeRate/ExchangeRateInstall.php' => '<?php',
            'modules/Premium/Mixed/New/module.json' => '{}',
            'modules/Premium/Mixed/Old/OldInstall.php' => '<?php',
            'modules/Base/Box/BoxInstall.php' => '<?php',
            'modules/Utils/RecordBrowser/RecordBrowser_0.php' => '<?php',
        ];
        foreach ($files as $path => $content) {
            if (! is_dir(dirname($this->root.'/'.$path))) {
                mkdir(dirname($this->root.'/'.$path), 0700, true);
            }
            file_put_contents($this->root.'/'.$path, $content);
        }
    }

    protected function tearDown(): void
    {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function test_legacy_module_folders_move_to_legacy_modules_and_epesi_modules_stay(): void
    {
        $result = (new LegacyModulesMover)->move($this->root);

        self::assertEqualsCanonicalizing(['modules/Base', 'modules/Premium/ExchangeRate', 'modules/Premium/Mixed/Old', 'modules/Utils'], $result['moved']);
        self::assertSame([], $result['skipped']);
        self::assertFileExists($this->root.'/legacy-modules/Premium/ExchangeRate/ExchangeRateInstall.php');
        self::assertFileExists($this->root.'/legacy-modules/Base/Box/BoxInstall.php');
        self::assertFileExists($this->root.'/legacy-modules/Premium/Mixed/Old/OldInstall.php');
        self::assertFileExists($this->root.'/modules/Epesi/CRM/Contacts/module.json');
        self::assertFileExists($this->root.'/modules/Premium/Equipment/Service/module.json');
        self::assertFileExists($this->root.'/modules/Premium/Mixed/New/module.json');
        self::assertDirectoryDoesNotExist($this->root.'/modules/Base');
    }

    public function test_an_existing_target_is_never_overwritten(): void
    {
        mkdir($this->root.'/legacy-modules/Base', 0700, true);
        file_put_contents($this->root.'/legacy-modules/Base/keep.txt', 'mine');

        $result = (new LegacyModulesMover)->move($this->root);

        self::assertContains('modules/Base', $result['skipped']);
        self::assertFileExists($this->root.'/modules/Base/Box/BoxInstall.php');
        self::assertSame('mine', file_get_contents($this->root.'/legacy-modules/Base/keep.txt'));
    }
}
