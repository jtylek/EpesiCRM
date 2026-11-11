<?php

use Epesi\Modules\MigrationWizard\Backup\BackupVerifier;
use Epesi\Modules\MigrationWizard\Detection\ModuleCoverage;
use PHPUnit\Framework\TestCase;

final class MigrationWizardSafetyTest extends TestCase
{
    public function test_backup_checksum_detects_corruption(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'epesi-backup-');
        try {
            file_put_contents($path, 'backup fixture');
            $hash = hash_file('sha256', $path);
            self::assertTrue((new BackupVerifier)->verify($path, $hash)['checksum_verified']);
            file_put_contents($path, 'tampered');
            $this->expectException(RuntimeException::class);
            (new BackupVerifier)->verify($path, $hash);
        } finally {
            @unlink($path);
        }
    }

    public function test_unknown_tables_are_never_reported_as_covered(): void
    {
        $result = (new ModuleCoverage)->summarize(['variables', 'modules', 'custom_invoice']);
        self::assertFalse($result['import_coverage_verified']);
        self::assertContains('custom_invoice', $result['unclassified_tables']);
    }
}
