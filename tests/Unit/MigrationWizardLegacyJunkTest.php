<?php

use Epesi\Modules\MigrationWizard\Cleanup\LegacyJunk;
use PHPUnit\Framework\TestCase;

final class MigrationWizardLegacyJunkTest extends TestCase
{
    private string $data;

    protected function setUp(): void
    {
        $this->data = sys_get_temp_dir().'/epesi-junk-'.bin2hex(random_bytes(5)).'/data';
        $files = [
            // Junk.
            'cache/minify/a.js' => 'aaaa', 'cache/index.html' => '', 'cache/.htaccess' => 'deny',
            'logs/cron.log' => 'log', 'tmp-sessions/sess_1' => 's',
            'CRM_Roundcube/tmp/x' => 'x', 'CRM_Roundcube/log/errors' => 'e',
            'Base_Theme/compiled/t.php' => 't', 'Base_Theme/cache/c' => 'c',
            'Base_Error/2026-01-01.txt' => 'err', 'Libs_TCPDF/__tcpdf_abc' => 'p',
            'Utils_FileUpload/stuck' => 'u', 'php_errors.log' => 'l', 'config.php.old' => 'secret', 'config.php.memcache_kt' => 'secret',
            // Kept.
            'config.php' => '<?php', 'cron_token.php' => '<?php', 'index.html' => '',
            'Base_Error/index.html' => '', 'Libs_TCPDF/company_logo.png' => 'png',
            'Base_Lang/custom/de.php' => '<?php', 'Base_MainModuleIndicator/logo.png' => 'png',
            'Base_Theme/templates/x.tpl' => 'tpl', 'CRM_Roundcube/config.php' => '<?php',
            'Utils_Attachment/1/2' => 'file', 'Utils_FileStorage/3/4' => 'file', 'CRM_Mail/attachments/0/a' => 'mail',
        ];
        foreach ($files as $path => $content) {
            @mkdir(dirname($this->data.'/'.$path), 0700, true);
            file_put_contents($this->data.'/'.$path, $content);
        }
    }

    protected function tearDown(): void
    {
        $root = dirname($this->data);
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }

    public function test_the_plan_names_only_the_junk(): void
    {
        $plan = (new LegacyJunk)->plan($this->data);
        $paths = $plan['paths'];
        sort($paths);

        self::assertSame([
            'Base_Error/2026-01-01.txt', 'Base_Theme/cache/c', 'Base_Theme/compiled/t.php', 'CRM_Roundcube/log/errors', 'CRM_Roundcube/tmp/x',
            'Libs_TCPDF/__tcpdf_abc', 'Utils_FileUpload/stuck', 'cache/minify/a.js', 'config.php.memcache_kt', 'config.php.old', 'logs/cron.log',
            'php_errors.log', 'tmp-sessions/sess_1',
        ], $paths);
        self::assertSame(13, $plan['files']);
        self::assertSame(30, $plan['bytes']);
        self::assertFileExists($this->data.'/cache/minify/a.js', 'a plan removes nothing');
    }

    public function test_purge_removes_the_junk_and_keeps_everything_else(): void
    {
        $calls = [];
        $result = (new LegacyJunk)->purge($this->data, static function (int $done, int $all) use (&$calls): void {
            $calls[] = [$done, $all];
        });

        self::assertSame(['files' => 13, 'bytes' => 30, 'skipped' => 0], $result);
        self::assertSame([13, 13], end($calls));
        self::assertSame([], (new LegacyJunk)->plan($this->data)['paths']);

        // A listed folder stays, with its own index.html and .htaccess; the sub-folders it emptied go.
        self::assertFileExists($this->data.'/cache/index.html');
        self::assertFileExists($this->data.'/cache/.htaccess');
        self::assertDirectoryDoesNotExist($this->data.'/cache/minify');
        self::assertDirectoryExists($this->data.'/logs');

        foreach (['config.php', 'cron_token.php', 'index.html', 'Base_Error/index.html', 'Libs_TCPDF/company_logo.png', 'Base_Lang/custom/de.php',
            'Base_MainModuleIndicator/logo.png', 'Base_Theme/templates/x.tpl', 'CRM_Roundcube/config.php', 'Utils_Attachment/1/2',
            'Utils_FileStorage/3/4', 'CRM_Mail/attachments/0/a'] as $kept) {
            self::assertFileExists($this->data.'/'.$kept, $kept);
        }
    }

    public function test_empty_folders_are_counted_at_any_depth_without_removing_any(): void
    {
        foreach (['Utils_Attachment/10', 'Utils_Attachment/x/y/z'] as $empty) {
            mkdir($this->data.'/'.$empty, 0700, true);
        }

        // 10, x, x/y and x/y/z; Utils_Attachment/1 holds a file.
        self::assertSame(4, (new LegacyJunk)->emptyFolders($this->data.'/Utils_Attachment'));
        self::assertDirectoryExists($this->data.'/Utils_Attachment/x/y/z');
        self::assertSame(0, (new LegacyJunk)->emptyFolders($this->data.'/not_there'));
    }

    public function test_purge_removes_the_empty_folders_of_utils_attachment_and_none_of_its_files(): void
    {
        foreach (['Utils_Attachment/10', 'Utils_Attachment/premium_products/100/old', 'Utils_FileStorage/empty', 'Base_Lang/empty'] as $empty) {
            mkdir($this->data.'/'.$empty, 0700, true);
        }
        file_put_contents($this->data.'/Utils_Attachment/.htaccess', 'deny');
        file_put_contents($this->data.'/Utils_Attachment/premium_products/83', 'photo');

        $plan = (new LegacyJunk)->plan($this->data);
        self::assertSame([], array_values(array_filter($plan['paths'], static fn (string $path): bool => str_starts_with($path, 'Utils_Attachment'))), 'no file there is junk');

        $result = (new LegacyJunk)->purge($this->data);

        self::assertSame(13, $result['files'], 'the empty folders are not counted as removed files');
        self::assertDirectoryDoesNotExist($this->data.'/Utils_Attachment/10');
        self::assertDirectoryDoesNotExist($this->data.'/Utils_Attachment/premium_products/100');
        foreach (['.htaccess', 'premium_products/83', '1/2'] as $kept) {
            self::assertFileExists($this->data.'/Utils_Attachment/'.$kept, $kept);
        }
        self::assertDirectoryExists($this->data.'/Utils_FileStorage/empty', 'only the listed folder is pruned');
        self::assertDirectoryExists($this->data.'/Base_Lang/empty', 'only the listed folder is pruned');
    }
}
