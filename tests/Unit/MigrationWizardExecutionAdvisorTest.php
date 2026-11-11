<?php

use Epesi\Modules\MigrationWizard\Checks\MigrationExecutionAdvisor;
use Epesi\Modules\MigrationWizard\Detection\ScratchAreas;
use PHPUnit\Framework\TestCase;

final class MigrationWizardExecutionAdvisorTest extends TestCase
{
    public function test_large_database_recommends_cli(): void
    {
        $root = sys_get_temp_dir().'/epesi-advisor-'.bin2hex(random_bytes(5));
        mkdir($root.'/data', 0700, true);
        try {
            $result = (new MigrationExecutionAdvisor)->assess([
                'table_statistics' => [['estimated_bytes' => 100000000, 'estimated_rows' => 2]],
            ], $root);
            self::assertTrue($result['cli_recommended']);
            self::assertSame('cli', $result['recommended_execution']);
            self::assertStringContainsString('production migration execution', $result['cli_instructions']);
        } finally {
            rmdir($root.'/data');
            rmdir($root);
        }
    }

    public function test_one_hundred_thousand_rows_recommends_cli(): void
    {
        $root = sys_get_temp_dir().'/epesi-advisor-'.bin2hex(random_bytes(5));
        mkdir($root.'/data', 0700, true);
        try {
            $result = (new MigrationExecutionAdvisor)->assess([
                'table_statistics' => [['estimated_bytes' => 1024, 'estimated_rows' => 100000]],
            ], $root);
            self::assertTrue($result['cli_recommended']);
        } finally {
            rmdir($root.'/data');
            rmdir($root);
        }
    }

    public function test_roundcubes_temporary_files_are_not_counted(): void
    {
        $root = sys_get_temp_dir().'/epesi-advisor-'.bin2hex(random_bytes(5));
        mkdir($root.'/data/CRM_Roundcube/tmp', 0700, true);
        file_put_contents($root.'/data/CRM_Roundcube/index.html', 'i');
        file_put_contents($root.'/data/CRM_Roundcube/tmp/upload1', 'u');
        file_put_contents($root.'/data/CRM_Roundcube/tmp/upload2', 'u');
        try {
            $result = (new MigrationExecutionAdvisor)->assess(['table_statistics' => []], $root, 100);
            self::assertSame(1, $result['files_scanned'], 'only index.html; tmp/ is scratch');
            self::assertTrue(ScratchAreas::skips($root.'/data', $root.'/data/CRM_Roundcube/tmp'));
            self::assertFalse(ScratchAreas::skips($root.'/data', $root.'/data/CRM_Roundcube'));
        } finally {
            foreach (['CRM_Roundcube/index.html', 'CRM_Roundcube/tmp/upload1', 'CRM_Roundcube/tmp/upload2'] as $f) {
                unlink($root.'/data/'.$f);
            }
            rmdir($root.'/data/CRM_Roundcube/tmp');
            rmdir($root.'/data/CRM_Roundcube');
            rmdir($root.'/data');
            rmdir($root);
        }
    }

    public function test_many_files_recommend_cli_and_cache_and_logs_are_not_counted(): void
    {
        $root = sys_get_temp_dir().'/epesi-advisor-'.bin2hex(random_bytes(5));
        mkdir($root.'/data/cache', 0700, true);
        mkdir($root.'/data/logs', 0700, true);
        file_put_contents($root.'/data/a.txt', 'a');
        file_put_contents($root.'/data/b.txt', 'b');
        file_put_contents($root.'/data/cache/c.txt', 'c');
        file_put_contents($root.'/data/logs/d.txt', 'd');
        try {
            $result = (new MigrationExecutionAdvisor)->assess(['table_statistics' => []], $root, 2);
            self::assertTrue($result['cli_recommended']);
            self::assertFalse($result['file_scan_incomplete']);
            self::assertSame(2, $result['files_scanned']);
        } finally {
            foreach (['a.txt', 'b.txt', 'cache/c.txt', 'logs/d.txt'] as $f) {
                unlink($root.'/data/'.$f);
            }
            rmdir($root.'/data/cache');
            rmdir($root.'/data/logs');
            rmdir($root.'/data');
            rmdir($root);
        }
    }
}
