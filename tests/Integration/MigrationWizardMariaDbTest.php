<?php

use Epesi\Modules\MigrationWizard\Detection\LegacyConfigReader;
use Epesi\Modules\MigrationWizard\Detection\LegacyInstallation;
use Epesi\Modules\MigrationWizard\Detection\ModuleCoverage;
use Epesi\Modules\MigrationWizard\Detection\SchemaFingerprint;
use PHPUnit\Framework\TestCase;

final class MigrationWizardMariaDbTest extends TestCase
{
    public function test_assessment_reads_real_mariadb_schema_without_modification(): void
    {
        if (getenv('EPESI_MIGRATION_INTEGRATION') !== '1') {
            self::markTestSkipped('Requires an isolated CI MariaDB service.');
        }
        $host = getenv('MIGRATION_TEST_DB_HOST') ?: '127.0.0.1';
        $port = getenv('MIGRATION_TEST_DB_PORT') ?: '3306';
        $db = getenv('MIGRATION_TEST_DB_NAME') ?: 'migration_fixture';
        $user = getenv('MIGRATION_TEST_DB_USER') ?: 'fixture_reader';
        $pass = getenv('MIGRATION_TEST_DB_PASSWORD') ?: '';
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $before = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_COLUMN);
        $config = tempnam(sys_get_temp_dir(), 'epesi-legacy-fixture-');
        try {
            $definitions = ['DATABASE_HOST' => $host, 'DATABASE_PORT' => $port,
                'DATABASE_USER' => $user, 'DATABASE_PASSWORD' => $pass, 'DATABASE_NAME' => $db];
            $source = "<?php\n";
            foreach ($definitions as $key => $value) {
                $source .= 'define('.var_export($key, true).', '.var_export($value, true).");\n";
            }
            file_put_contents($config, $source);
            $result = (new LegacyInstallation(new LegacyConfigReader, new SchemaFingerprint, new ModuleCoverage))->assess($config);
            self::assertTrue($result['legacy_signature']);
            self::assertFalse($result['compatible']);
            self::assertContains('custom_invoice', $result['coverage']['unclassified_tables']);
            self::assertGreaterThanOrEqual(5, $result['table_count']);
            self::assertNotEmpty($result['fingerprint']);
            $after = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_COLUMN);
            self::assertSame($before, $after);
        } finally {
            @unlink($config);
        }
    }
}
