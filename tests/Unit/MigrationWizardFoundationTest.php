<?php

use Epesi\Modules\MigrationWizard\Detection\LegacyConfigReader;
use Epesi\Modules\MigrationWizard\Detection\SchemaFingerprint;
use Epesi\Modules\MigrationWizard\State\MigrationState;
use PHPUnit\Framework\TestCase;

final class MigrationWizardFoundationTest extends TestCase
{
    public function test_schema_fingerprint_is_order_independent(): void
    {
        $f = new SchemaFingerprint;
        self::assertSame($f->hash(['a' => ['id'], 'b' => ['x', 'y']]), $f->hash(['b' => ['y', 'x'], 'a' => ['id']]));
    }

    public function test_legacy_configuration_is_never_executed(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'legacy-config-');
        $marker = $path.'.executed';
        try {
            file_put_contents($path, "<?php file_put_contents(".var_export($marker, true).", 'bad'); define('DATABASE_HOST', 'localhost'); define('DATABASE_USER', 'test'); define('DATABASE_NAME', 'epesi');");
            $result = (new LegacyConfigReader)->read($path);
            self::assertSame(3306, $result['DATABASE_PORT']);
            self::assertFileDoesNotExist($marker);
        } finally {
            @unlink($path);
            @unlink($marker);
        }
    }

    public function test_state_disallows_destructive_phases_and_stale_versions(): void
    {
        $dir = sys_get_temp_dir().'/epesi-state-'.bin2hex(random_bytes(8));
        try {
            $state = new MigrationState($dir);
            self::assertSame(1, $state->update(0, ['phase' => 'assessed'])['version']);
            foreach ([['version' => 1, 'phase' => 'cleaned'], ['version' => 0, 'phase' => 'assessed']] as $case) {
                try {
                    $state->update($case['version'], ['phase' => $case['phase']]);
                    self::fail('Unsafe state transition was accepted.');
                } catch (RuntimeException $e) {
                    self::assertNotSame('', $e->getMessage());
                }
            }
        } finally {
            @unlink($dir.'/state.json');
            @unlink($dir.'/state.lock');
            @rmdir($dir);
        }
    }
}
