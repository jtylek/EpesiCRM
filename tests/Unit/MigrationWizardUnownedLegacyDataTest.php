<?php

use Epesi\Modules\MigrationWizard\Database\LegacyModuleTables;
use Epesi\Modules\MigrationWizard\Database\UnownedLegacyData;
use PHPUnit\Framework\TestCase;

final class MigrationWizardUnownedLegacyDataTest extends TestCase
{
    public function test_only_namespaced_tables_are_dropped(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE legacy_old_module (id INTEGER)');
        $pdo->exec('CREATE TABLE companies (id INTEGER)');

        self::assertSame(['legacy_old_module', 'legacy_gone_already'], (new UnownedLegacyData)->dropTables($pdo, ['legacy_old_module', 'legacy_gone_already']));
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['companies'], $tables);

        $this->expectException(RuntimeException::class);
        (new UnownedLegacyData)->dropTables($pdo, ['companies']);
    }

    public function test_only_premium_and_custom_tables_and_the_obsolete_ones_are_not_removed_here(): void
    {
        foreach (['base_acl_rules', 'session', 'rc_mails_data_1', 'rc_related_data_1', 'user_login', 'utils_attachment_data_1', 'utils_filestorage', 'task_related_data_1', 'phpgacl', 'mystery'] as $table) {
            self::assertTrue(UnownedLegacyData::removable($table), $table);
        }
        foreach (['premium_timesheet_data_1', 'premium_exchangerate_data_1', 'custom_anything', 'rc_users', 'rc_cache', 'rc_contacts'] as $table) {
            self::assertFalse(UnownedLegacyData::removable($table), $table);
        }
    }

    public function test_tables_the_modules_that_stay_create_are_read_without_running_their_install_files(): void
    {
        $source = <<<'PHP'
            <?php
            throw new Exception('must not run');
            Utils_RecordBrowserCommon::install_new_recordset('shop_orders', $fields);
            DB::CreateTable('shop_log', 'id I4');
            DB::Execute('CREATE TABLE IF NOT EXISTS `shop_raw` (id int)');
            Utils_RecordBrowserCommon::install_new_recordset($dynamic, $fields);
            // install_new_recordset('commented_out');
            PHP;
        $root = sys_get_temp_dir().'/epesi-tables-'.bin2hex(random_bytes(5));
        mkdir($root.'/legacy-modules/Premium/Shop', 0700, true);
        file_put_contents($root.'/legacy-modules/Premium/Shop/ShopInstall.php', $source);

        $tables = (new LegacyModuleTables)->declared($root);

        unlink($root.'/legacy-modules/Premium/Shop/ShopInstall.php');
        rmdir($root.'/legacy-modules/Premium/Shop');
        rmdir($root.'/legacy-modules/Premium');
        rmdir($root.'/legacy-modules');
        rmdir($root);

        foreach (['shop_orders_data_1', 'shop_orders_edit_history', 'shop_log', 'shop_raw'] as $table) {
            self::assertArrayHasKey($table, $tables);
            self::assertFalse(UnownedLegacyData::removable($table, $tables), $table);
        }
        self::assertArrayNotHasKey('commented_out_data_1', $tables);
        self::assertTrue(UnownedLegacyData::removable('other_table', $tables));
    }
}
