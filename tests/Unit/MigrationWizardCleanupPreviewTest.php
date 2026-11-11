<?php

use Epesi\Modules\MigrationWizard\Cleanup\CleanupPreview;
use PHPUnit\Framework\TestCase;

final class MigrationWizardCleanupPreviewTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/epesi-preview-'.bin2hex(random_bytes(5));
        mkdir($this->root.'/data/Utils_Attachment/1', 0700, true);
        mkdir($this->root.'/data/Utils_Attachment/premium_shop/7', 0700, true);
        file_put_contents($this->root.'/data/Utils_Attachment/premium_shop/7/83', 'photo');
    }

    protected function tearDown(): void
    {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function test_it_says_what_goes_and_what_stays_with_the_reason_and_changes_nothing(): void
    {
        $pdo = new PDO('sqlite::memory:');
        foreach (['legacy_rc_mails_data_1', 'legacy_rc_mails_field', 'legacy_old_thing', 'legacy_premium_shop_data_1', 'legacy_user_login', 'companies'] as $table) {
            $pdo->exec("CREATE TABLE {$table} (id INTEGER)");
        }
        $pdo->exec('INSERT INTO legacy_rc_mails_data_1 VALUES (1), (2)');
        $map = ['rc_mails_data_1' => 'legacy_rc_mails_data_1', 'rc_mails_field' => 'legacy_rc_mails_field', 'old_thing' => 'legacy_old_thing', 'premium_shop_data_1' => 'legacy_premium_shop_data_1', 'user_login' => 'legacy_user_login'];
        $verification = [
            'cleanup_allowed' => true,
            'blocking' => [],
            'warnings' => [],
            'unowned_tables' => ['old_thing'],
            'obsolete_tables' => [],
            'preserved_tables' => ['premium_shop_data_1'],
            'table_groups' => ['rc_mails_data_1' => ['rc_mails_field']],
            'table_certification' => [['label' => 'Mail', 'table' => 'rc_mails_data_1', 'source' => 2, 'imported' => 2, 'skipped' => 0, 'skip_label' => null, 'missing_ids' => 0, 'pending' => [], 'status' => 'certified']],
        ];
        $plan = [
            'drop_tables' => ['legacy_old_thing', 'legacy_rc_mails_field', 'legacy_rc_mails_data_1'],
            'kept_tables' => ['user_login' => 'still referenced by premium_shop_recent'],
            'remove_paths' => [],
            'archive_paths' => [],
        ];

        $preview = new CleanupPreview;
        $result = $preview->build($pdo, $this->root, $verification, $plan, $map);
        $tables = array_column($result['tables'], null, 'table');

        self::assertArrayNotHasKey('companies', $tables, 'only legacy tables are listed');
        self::assertSame(['drop', 2, 'counts match'], [$tables['legacy_rc_mails_data_1']['decision'], $tables['legacy_rc_mails_data_1']['rows'], $tables['legacy_rc_mails_data_1']['why']]);
        self::assertSame(['drop', 'goes with rc_mails_data_1'], [$tables['legacy_rc_mails_field']['decision'], $tables['legacy_rc_mails_field']['why']]);
        self::assertSame('no importer and no Premium or Custom module owns it', $tables['legacy_old_thing']['why']);
        self::assertSame(['keep', 'a Premium or Custom module: no importer yet'], [$tables['legacy_premium_shop_data_1']['decision'], $tables['legacy_premium_shop_data_1']['why']]);
        self::assertSame(['keep', 'still referenced by premium_shop_recent'], [$tables['legacy_user_login']['decision'], $tables['legacy_user_login']['why']]);

        // 1, premium_shop/7 holds a file: only 1 is empty.
        self::assertSame(1, $result['empty_folders']['data/Utils_Attachment']);
        self::assertSame(1, $result['files_left_in_data']);
        self::assertDirectoryExists($this->root.'/data/Utils_Attachment/1');
        self::assertSame('2', (string) $pdo->query('SELECT COUNT(*) FROM legacy_rc_mails_data_1')->fetchColumn());

        $text = implode("\n", $preview->toLines($result));
        self::assertStringContainsString('2 = 2 imported', $text);
        self::assertStringContainsString('TABLES THAT WOULD BE DROPPED: 3 tables, 2 rows', $text);
    }
}
