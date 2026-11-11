<?php

use Epesi\Modules\MigrationWizard\Backup\BackupVerifier;
use Epesi\Modules\MigrationWizard\Backup\SqlDumpSafety;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * CI-only round trip on two disposable MariaDB schemas.
 * Never accepts a production database name or host from the browser.
 */
final class MigrationWizardDumpRestoreTest extends TestCase
{
    public function test_disposable_dump_can_be_restored_into_separate_database(): void
    {
        if (getenv('EPESI_MIGRATION_INTEGRATION') !== '1') {
            self::markTestSkipped('Isolated MariaDB integration environment required.');
        }
        $host = getenv('MIGRATION_TEST_DB_HOST') ?: '127.0.0.1';
        $port = getenv('MIGRATION_TEST_DB_PORT') ?: '3306';
        $source = 'migration_fixture';
        $target = 'migration_restore_fixture';
        $user = getenv('MIGRATION_TEST_DB_ADMIN') ?: 'root';
        $pass = getenv('MIGRATION_TEST_DB_ADMIN_PASSWORD') ?: '';
        $options = tempnam(sys_get_temp_dir(), 'epesi-restore-options-');
        $dump = tempnam(sys_get_temp_dir(), 'epesi-restore-dump-');
        try {
            chmod($options, 0600);
            $quote = static fn (string $value): string => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value).'"';
            file_put_contents($options, "[client]\nhost=".$quote($host)."\nport=".$quote($port)."\nuser=".$quote($user)."\npassword=".$quote($pass)."\n");
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$source;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `migration_restore_fixture`');
            $restore = new PDO("mysql:host=$host;port=$port;dbname=$target;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            self::assertSame(0, (int) $restore->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'migration_restore_fixture'")->fetchColumn());

            $export = new Process(['mysqldump', '--defaults-extra-file='.$options, '--single-transaction', '--quick', '--skip-add-drop-database', $source]);
            $export->mustRun();
            file_put_contents($dump, $export->getOutput());
            $verified = (new BackupVerifier)->verify($dump, hash_file('sha256', $dump));
            self::assertTrue($verified['checksum_verified']);
            $preflight = (new SqlDumpSafety)->inspect($dump);
            self::assertTrue($preflight['target_neutral_preflight'], 'Dump must not select or create another database.');

            // Only CI test fixtures are restored. Never call the production RestoreVerifier.
            $import = new Process(['mysql', '--defaults-extra-file='.$options, $target]);
            $import->setInput(file_get_contents($dump));
            $import->mustRun();
            self::assertSame(1, (int) $restore->query('SELECT COUNT(*) FROM custom_invoice')->fetchColumn());
            self::assertSame(1, (int) $restore->query('SELECT COUNT(*) FROM modules')->fetchColumn());
        } finally {
            @unlink($options);
            @unlink($dump);
        }
    }
}
