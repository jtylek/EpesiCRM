<?php

use App\Support\Setup\LegacyBootDetector;
use PHPUnit\Framework\TestCase;

final class MigrationWizardMilestone4Test extends TestCase
{
    public function test_first_boot_detects_legacy_without_executing_php(): void
    {
        $root = sys_get_temp_dir().'/epesi-boot-'.bin2hex(random_bytes(6));
        mkdir($root.'/data', 0700, true);
        $marker = $root.'/executed';
        try {
            self::assertSame('fresh', LegacyBootDetector::detect($root));
            file_put_contents($root.'/data/config.php', '<?php file_put_contents('.var_export($marker, true).", 'bad'); define('DATABASE_HOST','localhost'); define('DATABASE_USER','legacy'); define('DATABASE_NAME','epesi');");
            self::assertSame('legacy', LegacyBootDetector::detect($root));
            self::assertFileDoesNotExist($marker);
        } finally {
            @unlink($root.'/data/config.php');
            @rmdir($root.'/data');
            @rmdir($root);
        }
    }
}
