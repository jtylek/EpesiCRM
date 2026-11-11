<?php

use Epesi\Modules\MigrationWizard\Checks\VendorResidue;
use PHPUnit\Framework\TestCase;

final class MigrationWizardVendorResidueTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/epesi-vendor-residue-'.bin2hex(random_bytes(5));
        mkdir($this->root.'/vendor/composer', 0700, true);
        mkdir($this->root.'/vendor/laravel/framework', 0700, true);
        mkdir($this->root.'/vendor/bin', 0700, true);
        file_put_contents($this->root.'/vendor/composer/installed.json', json_encode([
            'packages' => [
                ['name' => 'laravel/framework'],
                ['name' => 'symfony/console'], // no folder on disk: that's fine, nothing to flag for it
            ],
        ]));
        file_put_contents($this->root.'/vendor/autoload.php', '<?php');
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->root)) {
            return;
        }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function test_legacy_only_organisation_folders_are_found_and_shipped_ones_are_not(): void
    {
        mkdir($this->root.'/vendor/ezyang', 0700, true);
        mkdir($this->root.'/vendor/enyo', 0700, true);

        $found = (new VendorResidue)->detect($this->root);

        self::assertSame(['enyo', 'ezyang'], $found);
    }

    public function test_composer_and_bin_and_the_autoloader_files_are_never_flagged(): void
    {
        touch($this->root.'/vendor/autoload_classmap.php');
        touch($this->root.'/vendor/installed.php');

        self::assertSame([], (new VendorResidue)->detect($this->root));
    }

    public function test_a_plain_file_at_the_top_of_vendor_is_ignored(): void
    {
        file_put_contents($this->root.'/vendor/ezyang.txt', 'not a folder');

        self::assertSame([], (new VendorResidue)->detect($this->root));
    }

    public function test_with_no_installed_json_or_no_vendor_folder_nothing_is_reported(): void
    {
        unlink($this->root.'/vendor/composer/installed.json');
        mkdir($this->root.'/vendor/ezyang', 0700, true);
        self::assertSame([], (new VendorResidue)->detect($this->root));

        self::assertSame([], (new VendorResidue)->detect($this->root.'/does-not-exist'));
    }
}
