<?php

use Epesi\Modules\MigrationWizard\Cleanup\LegacyCleanup;
use Epesi\Modules\MigrationWizard\Cleanup\LegacyFileClassifier;
use PHPUnit\Framework\TestCase;

/**
 * Cleanup on real scratch trees: it removes what is not Epesi 3, not hosting-owned and not preserved, and
 * keeps everything else exactly where it is.
 */
final class MigrationWizardLegacyCleanupTest extends TestCase
{
    private string $base;

    private string $root;

    private string $archives;

    /** Files of the release (update/MANIFEST.txt lists them). */
    private const RELEASE = [
        'index.php', 'cron.php', '.htaccess', 'artisan', 'composer.json', 'public/index.php', 'bootstrap/app.php', 'app/App.php',
        'vendor/autoload.php', 'vendor/laravel/framework/a.php', 'modules/Epesi/CRM/Contacts/src/C.php', 'config/app.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/epesi-cleanup-'.bin2hex(random_bytes(6));
        $this->root = $this->base.'/site';
        $this->archives = $this->base.'/archives';
        mkdir($this->archives, 0700, true);
        $files = [
            // The release and Epesi 3's own runtime.
            ...array_fill_keys(self::RELEASE, '<?php // epesi 3'),
            '.env' => 'APP_KEY=x', 'storage/logs/laravel.log' => 'log', 'bootstrap/cache/packages.php' => '<?php',
            'public/build/app.js' => 'js', 'public/epesi-webmail/index.php' => '<?php // downloaded roundcube',
            'update/MANIFEST.txt' => 'list', 'lang/pl.json' => '{}',
            // Hosting-owned.
            '.well-known/acme-challenge/token' => 'token', 'cgi-bin/script.cgi' => '#!/bin/sh', '.user.ini' => 'x=1', 'php.ini' => 'y=2',
            'error_log' => 'errors', 'softver.txt' => '1.9.1',
            // Legacy code that goes: stock root files, stale vendor files.
            'include/config.php' => '<?php // legacy core', 'include/libs/a.php' => '<?php // legacy lib', 'libs/x.php' => '<?php', 'images/a.png' => 'png',
            'setup.php' => '<?php // legacy', 'README.md' => 'legacy readme', 'composer.lock' => '{}', 'vendor/old/lib.php' => '<?php // stale', 'vendor/old/deep/b.php' => '<?php',
            // Nobody listed these: archived first.
            'my-notes.txt' => 'notes', 'extra/tool/run.php' => '<?php // added by someone',
            // modules/: a migrated legacy module goes; everything else stays.
            'modules/CRM/Contacts/ContactsCommon_0.php' => '<?php // migrated module', 'modules/CRM/Contacts/ContactsInstall.php' => '<?php',
            'modules/CRM/Contacts/Sub/Sub_0.php' => '<?php // a nested module nobody migrated',
            'modules/Base/Theme/Theme_0.php' => '<?php // core module with no importer',
            'modules/Premium/Warehouse/Warehouse_0.php' => '<?php // premium', 'modules/Custom/Invoice/Invoice_0.php' => '<?php // custom',
            'modules/Epesi/Stray/old.php' => '<?php // not listed, not installed',
            // data/: a migrated area goes, everything else stays.
            'data/config.php' => '<?php define("DATABASE_HOST", "x");', 'data/Photos/a.jpg' => 'jpg', 'data/Photos/deep/b.jpg' => 'jpg2',
            'data/Custom_Invoice/inv.pdf' => 'pdf', 'data/CRM_Mail/attachments/5/1.2' => 'left behind', 'data/CRM_Mail/encryption.key' => 'key',
        ];
        foreach ($files as $path => $content) {
            @mkdir(dirname($this->root.'/'.$path), 0700, true);
            file_put_contents($this->root.'/'.$path, $content);
        }
        mkdir($this->root.'/data/CRM_Mail/attachments/6/deep', 0700, true);
        mkdir($this->root.'/data/Utils_FileStorage/a/b', 0700, true);
        file_put_contents($this->base.'/outside.txt', 'must survive');
    }

    protected function tearDown(): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            ($entry->isDir() && ! $entry->isLink()) ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->base);
        parent::tearDown();
    }

    private function manifests(array $release = self::RELEASE): array
    {
        return [
            'release' => $release,
            'options' => ['installed_modules' => ['modules/Epesi/CRM/Contacts'], 'migrated_modules' => ['CRM_Contacts', 'Base_User']],
        ];
    }

    private function verification(array $override = []): array
    {
        return $override + [
            'status' => 'warn',
            'cleanup_allowed' => true,
            'cleanup_eligible_tables' => [],
            'table_groups' => [],
            'cleanup_eligible_file_areas' => ['data/Photos', 'data/CRM_Mail', 'data/Utils_FileStorage'],
            'discarded_file_areas' => [],
            'preserved_tables' => [],
        ];
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (['users', 'legacy_widget_data_1', 'legacy_widget_field', 'legacy_widget_access', 'legacy_gadget_data_1', 'legacy_gadget_field', 'legacy_unlisted'] as $table) {
            $pdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY)");
        }
        $pdo->exec('CREATE TABLE legacy_gadget_edit_history (id INTEGER PRIMARY KEY, gadget_id INTEGER REFERENCES legacy_gadget_data_1 (id))');

        return $pdo;
    }

    private function map(): array
    {
        return [
            'widget_data_1' => 'legacy_widget_data_1', 'widget_field' => 'legacy_widget_field', 'widget_access' => 'legacy_widget_access',
            'gadget_data_1' => 'legacy_gadget_data_1', 'gadget_field' => 'legacy_gadget_field', 'gadget_edit_history' => 'legacy_gadget_edit_history',
            'unlisted' => 'legacy_unlisted',
        ];
    }

    /** @return list<string> */
    private function tables(PDO $pdo): array
    {
        return $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    }

    private function cleanup(bool $enabled = true): LegacyCleanup
    {
        return new LegacyCleanup(new LegacyFileClassifier, $enabled);
    }

    private function execute(array $verification = [], ?PDO $pdo = null, array $manifests = []): array
    {
        return $this->cleanup()->execute($this->root, $this->archives, $manifests ?: $this->manifests(), $this->verification($verification), $this->map(), $pdo ?? $this->pdo(), true);
    }

    public function test_the_classes_say_what_stays_and_what_goes(): void
    {
        $class = (new LegacyFileClassifier)->classify($this->root, self::RELEASE, [
            'installed_modules' => ['modules/Epesi/CRM/Contacts'], 'migrated_modules' => ['CRM_Contacts'], 'releasable_areas' => ['data/Photos'],
        ]);
        $of = static fn (string $path): string => $class['entries'][$path]['class'] ?? 'absent';

        foreach (['index.php', 'vendor/laravel/framework/a.php', '.env', 'storage/logs/laravel.log', 'public/build/app.js', 'public/epesi-webmail/index.php', 'bootstrap/cache/packages.php', 'update/MANIFEST.txt', 'modules/Epesi/CRM/Contacts/src/C.php'] as $path) {
            self::assertSame('keep', $of($path), $path);
        }
        foreach (['vendor/old/lib.php', 'include/config.php', 'libs/x.php', 'setup.php', 'composer.lock', 'modules/CRM/Contacts/ContactsCommon_0.php', 'images/a.png'] as $path) {
            self::assertSame('legacy_code', $of($path), $path);
        }
        foreach (['my-notes.txt', 'extra/tool/run.php'] as $path) {
            self::assertSame('unknown', $of($path), $path);
        }
        foreach (['modules/Premium/Warehouse/Warehouse_0.php', 'modules/Custom/Invoice/Invoice_0.php', 'modules/Base/Theme/Theme_0.php', 'modules/CRM/Contacts/Sub/Sub_0.php', 'modules/Epesi/Stray/old.php', 'data/config.php', 'data/Custom_Invoice/inv.pdf', 'data/CRM_Mail/attachments/5/1.2', 'data/CRM_Mail/encryption.key'] as $path) {
            self::assertSame('preserved', $of($path), $path);
        }
        self::assertSame('legacy_data', $of('data/Photos/a.jpg'));
        // Hosting-owned paths are kept whole and reported, and nothing below them is even listed.
        self::assertSame(['.user.ini', '.well-known', 'cgi-bin', 'error_log', 'php.ini', 'softver.txt'], $class['hosting_kept']);
        self::assertArrayNotHasKey('cgi-bin/script.cgi', $class['entries']);
    }

    public function test_plan_lists_exactly_what_would_go_and_changes_nothing(): void
    {
        $plan = $this->cleanup(false)->plan($this->root, $this->manifests(), $this->verification(), $this->map());

        self::assertEqualsCanonicalizing([
            'composer.lock', 'README.md', 'setup.php', 'libs/x.php', 'images/a.png', 'include/config.php', 'include/libs/a.php',
            'vendor/old/lib.php', 'vendor/old/deep/b.php', 'my-notes.txt', 'extra/tool/run.php',
            'modules/CRM/Contacts/ContactsCommon_0.php', 'modules/CRM/Contacts/ContactsInstall.php',
            'data/Photos/a.jpg', 'data/Photos/deep/b.jpg', 'data/CRM_Mail/attachments/5/1.2',
        ], $plan['remove_paths']);
        self::assertEqualsCanonicalizing(['my-notes.txt', 'extra/tool/run.php'], $plan['archive_paths']);
        self::assertSame(4, $plan['modules_kept']);
        self::assertFileExists($this->root.'/include/config.php');
        self::assertFileExists($this->root.'/vendor/old/lib.php');
    }

    public function test_execute_removes_stale_vendor_and_root_legacy_code_and_keeps_everything_else(): void
    {
        $result = $this->execute();

        foreach (['include', 'libs', 'images', 'setup.php', 'README.md', 'composer.lock', 'vendor/old', 'my-notes.txt', 'extra', 'modules/CRM/Contacts/ContactsCommon_0.php', 'modules/CRM/Contacts/ContactsInstall.php', 'data/Photos'] as $gone) {
            self::assertFileDoesNotExist($this->root.'/'.$gone, $gone);
        }
        // Epesi 3 itself, its runtime and its modules.
        foreach (self::RELEASE as $kept) {
            self::assertFileExists($this->root.'/'.$kept, $kept);
        }
        foreach (['.env', 'storage/logs/laravel.log', 'bootstrap/cache/packages.php', 'public/build/app.js', 'public/epesi-webmail/index.php', 'update/MANIFEST.txt', 'lang/pl.json'] as $kept) {
            self::assertFileExists($this->root.'/'.$kept, $kept);
        }
        // Hosting-owned paths survive and are reported.
        foreach (['.well-known/acme-challenge/token', 'cgi-bin/script.cgi', '.user.ini', 'php.ini', 'error_log', 'softver.txt'] as $kept) {
            self::assertFileExists($this->root.'/'.$kept, $kept);
        }
        self::assertContains('cgi-bin', $result['hosting_kept']);
        self::assertContains('.well-known', $result['hosting_kept']);
        // Not migrated modules/ directories stay, counted: Contacts' nested module, Base/Theme, Premium/Warehouse, Custom/Invoice (Epesi/Stray is not a legacy module).
        foreach (['modules/CRM/Contacts/Sub/Sub_0.php', 'modules/Base/Theme/Theme_0.php', 'modules/Premium/Warehouse/Warehouse_0.php', 'modules/Custom/Invoice/Invoice_0.php', 'modules/Epesi/Stray/old.php'] as $kept) {
            self::assertFileExists($this->root.'/'.$kept, $kept);
        }
        self::assertSame(4, $result['modules_kept']);
        // Not migrated data/ areas stay, data/config.php with them.
        self::assertFileExists($this->root.'/data/config.php');
        self::assertFileExists($this->root.'/data/Custom_Invoice/inv.pdf');
        self::assertSame(16, $result['removed_files'], 'the orphan mail file included');
    }

    public function test_the_log_lists_every_removed_path_and_the_unlisted_files_are_archived_with_hashes(): void
    {
        $result = $this->execute();

        $log = (string) file_get_contents($result['log']);
        foreach (['remove include/config.php', 'remove vendor/old/lib.php', 'remove my-notes.txt', 'remove modules/CRM/Contacts/ContactsCommon_0.php', 'archived extra/tool/run.php', 'keep-hosting cgi-bin'] as $line) {
            self::assertStringContainsString($line, $log, $line);
        }
        self::assertStringNotContainsString('data/config.php', $log);
        self::assertStringNotContainsString('Premium', $log);

        // Only what nobody listed is archived (the legacy code is in the pre-upgrade backup).
        $manifest = json_decode(file_get_contents($result['archive']['manifest']), true);
        self::assertEqualsCanonicalizing(['my-notes.txt', 'extra/tool/run.php'], array_keys($manifest['files']));
        self::assertSame(hash('sha256', 'notes'), $manifest['files']['my-notes.txt']);
        self::assertSame(hash_file('sha256', $result['archive']['path']), $result['archive']['sha256']);
    }

    public function test_orphans_left_in_a_migrated_business_file_area_go_but_the_mail_key_stays(): void
    {
        $this->execute();

        self::assertFileDoesNotExist($this->root.'/data/CRM_Mail/attachments/5/1.2', 'no imported record uses it');
        self::assertFileExists($this->root.'/data/CRM_Mail/encryption.key');
        self::assertDirectoryDoesNotExist($this->root.'/data/CRM_Mail/attachments/6');
        self::assertDirectoryDoesNotExist($this->root.'/data/Utils_FileStorage', 'an area with nothing left goes with its empty directories');
    }

    public function test_a_discarded_area_is_archived_before_it_is_removed(): void
    {
        $result = $this->execute(['discarded_file_areas' => ['data/Custom_Invoice']]);

        self::assertFileDoesNotExist($this->root.'/data/Custom_Invoice');
        $manifest = json_decode(file_get_contents($result['archive']['manifest']), true);
        self::assertSame(hash('sha256', 'pdf'), $manifest['files']['data/Custom_Invoice/inv.pdf']);
    }

    public function test_epesi_3_root_files_stay_by_name_even_when_the_release_list_does_not_name_them(): void
    {
        $withoutArtisan = array_values(array_diff(self::RELEASE, ['artisan']));

        $this->execute(manifests: $this->manifests($withoutArtisan));

        self::assertFileExists($this->root.'/artisan', 'Epesi 3 root files are kept by name: the list is only a safety net');
        self::assertFileDoesNotExist($this->root.'/include/config.php', 'the legacy root folder still goes');
    }

    public function test_a_user_added_site_too_big_to_archive_refuses_instead_of_copying_it(): void
    {
        $big = fopen($this->root.'/other-site-backup.bin', 'wb');
        ftruncate($big, LegacyCleanup::ARCHIVE_MAX_BYTES + 1024);
        fclose($big);

        try {
            $this->execute();
            self::fail('Cleanup archived a file over the limit.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('outside the Epesi directory', $e->getMessage());
        }

        self::assertFileExists($this->root.'/other-site-backup.bin');
        self::assertFileExists($this->root.'/include/config.php');
        self::assertSame([], glob($this->archives.'/*') ?: []);
    }

    public function test_certified_tables_go_with_their_metadata_and_nothing_else_is_touched(): void
    {
        $pdo = $this->pdo();
        $result = $this->execute([
            'cleanup_eligible_tables' => ['widget_data_1', 'widget_field', 'widget_access'],
            'table_groups' => ['widget_data_1' => ['widget_access', 'widget_field']],
        ], $pdo);

        self::assertEqualsCanonicalizing(['legacy_widget_data_1', 'legacy_widget_field', 'legacy_widget_access'], $result['dropped_tables']);
        // Epesi 3's own table, tables nobody released and the other recordset are untouched.
        self::assertSame(['legacy_gadget_data_1', 'legacy_gadget_edit_history', 'legacy_gadget_field', 'legacy_unlisted', 'users'], $this->tables($pdo));
        self::assertSame([], $result['kept_tables']);
    }

    public function test_a_certified_table_that_another_table_still_references_stays_with_its_metadata_and_nothing_stops(): void
    {
        $pdo = $this->pdo();
        $result = $this->execute([
            'cleanup_eligible_tables' => ['widget_data_1', 'widget_field', 'gadget_data_1', 'gadget_field'],
            'table_groups' => ['widget_data_1' => ['widget_field'], 'gadget_data_1' => ['gadget_field']],
        ], $pdo);

        // gadget_edit_history (kept) references gadget_data_1, so the table stays, and its metadata stays with it.
        self::assertSame(['legacy_widget_data_1', 'legacy_widget_field'], $this->orderless($result['dropped_tables']));
        self::assertStringContainsString('still referenced by gadget_edit_history', $result['kept_tables']['gadget_data_1']);
        self::assertStringContainsString('gadget_data_1 is kept', $result['kept_tables']['gadget_field']);
        self::assertContains('legacy_gadget_data_1', $this->tables($pdo));
        self::assertContains('legacy_gadget_field', $this->tables($pdo));
        // The rest of cleanup ran regardless.
        self::assertFileDoesNotExist($this->root.'/include/config.php');
    }

    public function test_a_released_table_missing_from_the_rename_map_aborts_before_any_change(): void
    {
        $pdo = $this->pdo();
        try {
            $this->execute(['cleanup_eligible_tables' => ['guessed_by_prefix']], $pdo);
            self::fail('A table outside the rename map was released.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('rename map', $e->getMessage());
        }
        self::assertFileExists($this->root.'/include/config.php');
        self::assertContains('legacy_unlisted', $this->tables($pdo));
    }

    public function test_running_it_twice_is_safe(): void
    {
        $pdo = $this->pdo();
        $verification = ['cleanup_eligible_tables' => ['widget_data_1'], 'table_groups' => []];
        $first = $this->execute($verification, $pdo);
        $again = $this->execute($verification, $pdo);

        self::assertSame(['legacy_widget_data_1'], $first['dropped_tables']);
        self::assertSame([], $again['dropped_tables']);
        self::assertSame(0, $again['removed_files']);
        self::assertNull($again['archive']);
        self::assertFileExists($this->root.'/data/config.php');
        self::assertFileExists($this->root.'/modules/Premium/Warehouse/Warehouse_0.php');
    }

    public function test_a_link_in_legacy_code_is_unlinked_and_never_followed(): void
    {
        if (! @symlink($this->base.'/outside.txt', $this->root.'/include/link.txt')) {
            $this->markTestSkipped('Symbolic links cannot be created here.');
        }

        $this->execute();

        self::assertFileDoesNotExist($this->root.'/include/link.txt');
        self::assertSame('must survive', file_get_contents($this->base.'/outside.txt'));
    }

    public function test_cleanup_is_refused_when_disabled_unconfirmed_or_blocked(): void
    {
        $pdo = $this->pdo();
        $enabled = $this->cleanup();
        $cases = [
            'disabled' => [$this->cleanup(false), $this->verification(), true, $this->archives],
            'no backup' => [$enabled, $this->verification(), false, $this->archives],
            'blocked' => [$enabled, $this->verification(['cleanup_allowed' => false, 'status' => 'block']), true, $this->archives],
            'archive inside install' => [$enabled, $this->verification(), true, $this->root.'/storage'],
        ];
        foreach ($cases as $label => [$cleanup, $verification, $backup, $archive]) {
            try {
                $cleanup->execute($this->root, $archive, $this->manifests(), $verification, $this->map(), $pdo, $backup);
                self::fail("cleanup ran: {$label}");
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }
        self::assertFileExists($this->root.'/include/config.php');
        self::assertContains('legacy_unlisted', $this->tables($pdo));
    }

    /** @param  list<string>  $list */
    private function orderless(array $list): array
    {
        sort($list);

        return $list;
    }

    private function fkPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        foreach (['widget', 'gadget'] as $tab) {
            $pdo->exec("CREATE TABLE legacy_{$tab}_data_1 (id INTEGER PRIMARY KEY)");
            $pdo->exec("CREATE TABLE legacy_{$tab}_field (id INTEGER PRIMARY KEY)");
            $pdo->exec("CREATE TABLE legacy_{$tab}_edit_history (id INTEGER PRIMARY KEY, r INTEGER REFERENCES legacy_{$tab}_data_1 (id))");
            $pdo->exec("CREATE TABLE legacy_{$tab}_edit_history_data (id INTEGER PRIMARY KEY, e INTEGER REFERENCES legacy_{$tab}_edit_history (id))");
            $pdo->exec("CREATE TABLE legacy_{$tab}_favorite (id INTEGER PRIMARY KEY, r INTEGER REFERENCES legacy_{$tab}_data_1 (id))");
            $pdo->exec("CREATE TABLE legacy_{$tab}_recent (id INTEGER PRIMARY KEY, r INTEGER REFERENCES legacy_{$tab}_data_1 (id))");
        }

        return $pdo;
    }

    private function sideMap(): array
    {
        $map = [];
        foreach (['widget', 'gadget'] as $tab) {
            foreach (['data_1', 'field', 'edit_history', 'edit_history_data', 'favorite', 'recent'] as $suffix) {
                $map["{$tab}_{$suffix}"] = "legacy_{$tab}_{$suffix}";
            }
        }

        return $map;
    }

    private function sides(string $tab): array
    {
        return array_map(static fn (string $s): string => "{$tab}_{$s}", ['field', 'edit_history', 'edit_history_data', 'favorite', 'recent']);
    }

    public function test_all_side_tables_go_with_a_certified_parent_and_none_with_another_and_foreign_keys_are_back_on(): void
    {
        $pdo = $this->fkPdo();
        $verification = $this->verification([
            'cleanup_eligible_tables' => ['widget_data_1', ...$this->sides('widget')],
            'table_groups' => ['widget_data_1' => $this->sides('widget')],
        ]);

        $result = $this->cleanup()->execute($this->root, $this->archives, $this->manifests(), $verification, $this->sideMap(), $pdo, true);

        self::assertCount(6, $result['dropped_tables']);
        self::assertSame(['legacy_gadget_data_1', 'legacy_gadget_edit_history', 'legacy_gadget_edit_history_data', 'legacy_gadget_favorite', 'legacy_gadget_field', 'legacy_gadget_recent', 'users'], $this->tables($pdo));
        self::assertSame('1', (string) $pdo->query('PRAGMA foreign_keys')->fetchColumn(), 'foreign keys are on again');
    }

    public function test_foreign_key_checks_are_restored_after_a_failed_drop(): void
    {
        $pdo = $this->fkPdo();
        $verification = $this->verification(['cleanup_eligible_tables' => ['widget_data_1', 'ghost'], 'table_groups' => []]);
        $pdo->exec('CREATE TABLE legacy_ghost (id INTEGER)');
        // A view with the table's name makes DROP TABLE fail midway.
        $map = $this->sideMap() + ['ghost' => 'legacy_ghost'];
        $pdo->exec('DROP TABLE legacy_ghost');
        $pdo->exec('CREATE VIEW legacy_ghost AS SELECT 1 AS id');

        try {
            $this->cleanup()->execute($this->root, $this->archives, $this->manifests(), $verification, $map, $pdo, true);
            self::fail('The drop should have failed.');
        } catch (Throwable) {
        }

        self::assertSame('1', (string) $pdo->query('PRAGMA foreign_keys')->fetchColumn(), 'a failure never leaves foreign keys off');
    }

    public function test_a_dangling_reference_is_never_left_because_a_referenced_table_stays_when_a_kept_table_points_at_it(): void
    {
        $pdo = $this->fkPdo();
        // gadget's side tables are kept (uncertified), so gadget_data_1 cannot go even if it were released.
        $verification = $this->verification(['cleanup_eligible_tables' => ['gadget_data_1'], 'table_groups' => []]);

        $result = $this->cleanup()->execute($this->root, $this->archives, $this->manifests(), $verification, $this->sideMap(), $pdo, true);

        self::assertSame([], $result['dropped_tables']);
        self::assertStringContainsString('still referenced by', $result['kept_tables']['gadget_data_1']);
    }

    public function test_the_watchdog_group_is_dropped_at_cleanup_children_first(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE legacy_utils_watchdog_category (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE legacy_utils_watchdog_category_subscription (id INTEGER PRIMARY KEY, c INTEGER REFERENCES legacy_utils_watchdog_category (id))');
        $map = $this->map() + ['utils_watchdog_category' => 'legacy_utils_watchdog_category', 'utils_watchdog_category_subscription' => 'legacy_utils_watchdog_category_subscription'];

        $result = $this->cleanup()->execute($this->root, $this->archives, $this->manifests(), $this->verification(['cleanup_eligible_tables' => ['utils_watchdog_category', 'utils_watchdog_category_subscription'], 'table_groups' => []]), $map, $pdo, true);

        self::assertSame(['legacy_utils_watchdog_category_subscription', 'legacy_utils_watchdog_category'], $result['dropped_tables']);
        self::assertNotContains('legacy_utils_watchdog_category', $this->tables($pdo));
        self::assertContains('legacy_unlisted', $this->tables($pdo));
        self::assertContains('users', $this->tables($pdo));
    }

    public function test_the_multiple_addresses_tables_are_dropped_children_first_once_released(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE legacy_premium_multiple_addresses_data_1 (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE legacy_premium_multiple_addresses_edit_history (id INTEGER PRIMARY KEY, d INTEGER REFERENCES legacy_premium_multiple_addresses_data_1 (id))');
        $map = $this->map() + [
            'premium_multiple_addresses_data_1' => 'legacy_premium_multiple_addresses_data_1',
            'premium_multiple_addresses_edit_history' => 'legacy_premium_multiple_addresses_edit_history',
        ];
        $verification = $this->verification(['cleanup_eligible_tables' => ['premium_multiple_addresses_data_1', 'premium_multiple_addresses_edit_history'], 'table_groups' => ['premium_multiple_addresses_data_1' => ['premium_multiple_addresses_edit_history']]]);

        $result = $this->cleanup()->execute($this->root, $this->archives, $this->manifests(), $verification, $map, $pdo, true);

        self::assertSame(['legacy_premium_multiple_addresses_edit_history', 'legacy_premium_multiple_addresses_data_1'], $result['dropped_tables']);
        self::assertNotContains('legacy_premium_multiple_addresses_data_1', $this->tables($pdo));
    }

    public function test_backup_files_outside_data_are_left_in_place_listed_and_not_counted_toward_the_cap(): void
    {
        foreach (['site-backup.ZIP', 'dump.sql', 'old/db.sql.gz', 'x/y/z.tar.gz', 'a.tgz', 'b.bak', 'c.7z', 'd.rar', 'e.bz2', 'f.gz', 'g.tar'] as $name) {
            @mkdir(dirname($this->root.'/'.$name), 0700, true);
            file_put_contents($this->root.'/'.$name, 'backup');
        }
        // Even far over the byte cap they are neither archived nor refused.
        $big = fopen($this->root.'/huge-backup.tar', 'wb');
        ftruncate($big, LegacyCleanup::ARCHIVE_MAX_BYTES + 1024);
        fclose($big);

        $plan = $this->cleanup(false)->plan($this->root, $this->manifests(), $this->verification(), $this->map());
        $result = $this->execute();

        foreach (['site-backup.ZIP', 'dump.sql', 'old/db.sql.gz', 'x/y/z.tar.gz', 'a.tgz', 'b.bak', 'c.7z', 'd.rar', 'e.bz2', 'f.gz', 'g.tar', 'huge-backup.tar'] as $kept) {
            self::assertFileExists($this->root.'/'.$kept, $kept);
            self::assertContains($kept, $result['backups_left_in_place'], $kept);
            self::assertNotContains($kept, $plan['remove_paths']);
            self::assertNotContains($kept, $plan['archive_paths']);
        }
        $manifest = json_decode(file_get_contents($result['archive']['manifest']), true);
        self::assertSame(['extra/tool/run.php', 'my-notes.txt'], collect(array_keys($manifest['files']))->sort()->values()->all());
    }

    public function test_data_logs_and_cache_are_never_archived_or_counted_and_follow_the_data_rules(): void
    {
        foreach (['data/logs/a.log', 'data/cache/b.tmp'] as $name) {
            @mkdir(dirname($this->root.'/'.$name), 0700, true);
            file_put_contents($this->root.'/'.$name, 'x');
        }
        // Not migrated: they stay.
        $this->execute();
        self::assertFileExists($this->root.'/data/logs/a.log');
        self::assertFileExists($this->root.'/data/cache/b.tmp');

        // Approved for discard: removed without being archived, and a huge cache does not hit the cap.
        $big = fopen($this->root.'/data/cache/big.tmp', 'wb');
        ftruncate($big, LegacyCleanup::ARCHIVE_MAX_BYTES + 1024);
        fclose($big);
        $result = $this->execute(['discarded_file_areas' => ['data/logs', 'data/cache']]);

        self::assertFileDoesNotExist($this->root.'/data/logs/a.log');
        self::assertFileDoesNotExist($this->root.'/data/cache/big.tmp');
        $archived = $result['archive'] === null ? [] : array_keys(json_decode(file_get_contents($result['archive']['manifest']), true)['files']);
        self::assertSame([], array_values(array_filter($archived, static fn (string $p): bool => str_starts_with($p, 'data/logs') || str_starts_with($p, 'data/cache'))));
    }

    public function test_a_leftover_site_copy_in_a_folder_is_refused_naming_the_folder_and_telling_where_to_move_it(): void
    {
        for ($i = 0; $i < LegacyCleanup::ARCHIVE_MAX_FILES + 5; $i++) {
            $dir = $this->root.'/old-site-copy/d'.($i % 50);
            @mkdir($dir, 0700, true);
            file_put_contents($dir.'/f'.$i.'.txt', '');
        }

        try {
            $this->execute();
            self::fail('A folder with a whole copy of the site was archived.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('old-site-copy', $e->getMessage());
            self::assertStringContainsString('outside the Epesi directory', $e->getMessage());
        }

        self::assertFileExists($this->root.'/old-site-copy/d1/f1.txt');
        self::assertFileExists($this->root.'/include/config.php');
        self::assertSame([], glob($this->archives.'/*') ?: []);
    }
}
