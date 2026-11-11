<?php

use Epesi\Modules\MigrationWizard\Backup\BackupVerifier;
use Epesi\Modules\MigrationWizard\Backup\RestoreVerifier;
use PHPUnit\Framework\TestCase;

final class MigrationWizardRestoreSafetyTest extends TestCase
{
    public function test_refuses_source_database_as_restore_target(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'epesi-sql-');
        try {
            file_put_contents($path, '-- SQL fixture');
            $this->expectException(RuntimeException::class);
            (new RestoreVerifier(new BackupVerifier))->rehearse($path, hash_file('sha256', $path), [
                'host' => '127.0.0.1', 'port' => 3306, 'database' => 'epesi',
                'source_database' => 'epesi', 'user' => 'tester', 'password' => '',
            ]);
        } finally {
            @unlink($path);
        }
    }
}
