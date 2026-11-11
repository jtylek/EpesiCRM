<?php

use Epesi\Modules\MigrationWizard\Backup\SqlDumpSafety;
use PHPUnit\Framework\TestCase;

final class MigrationWizardSqlDumpSafetyTest extends TestCase
{
    public function test_detects_database_switching_and_never_authorizes_restore(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'epesi-dump-');
        try {
            file_put_contents($path, "-- SQL dump\nUSE production;\nCREATE TABLE demo (id INT);\n");
            $report = (new SqlDumpSafety)->inspect($path);
            self::assertFalse($report['target_neutral_preflight']);
            self::assertSame([2], $report['database_selection_hazards']);
            self::assertFalse($report['restore_authorized']);
            file_put_contents($path, "-- SQL dump\nCREATE TABLE demo (id INT);\n");
            $report = (new SqlDumpSafety)->inspect($path);
            self::assertTrue($report['target_neutral_preflight']);
            self::assertFalse($report['restore_authorized']);
        } finally {
            @unlink($path);
        }
    }
}
