<?php

namespace Tests\Feature;

use App\Services\Update\CoreUpdater;
use App\Services\Update\UpdateException;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class CoreUpdateTest extends TestCase
{
    protected string $base;

    protected string $zip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'epesi-core-update-'.bin2hex(random_bytes(4));
        $this->zip = $this->base.'.zip';

        $this->write('VERSION', '2.0.0RC1');
        $this->write('artisan', 'old artisan');
        $this->write('composer.json', '{}');
        $this->write('vendor/autoload.php', 'old autoload');
        $this->write('vendor/gone/Removed.php', 'no longer shipped');
        $this->write('vendor/same/Same.php', 'unchanged');
        $this->write('app/Models/Thing.php', 'old thing');
        $this->write('modules/Epesi/Mail/module.json', '{"v":1}');
        $this->write('modules/Epesi/Mail/old.php', 'removed with the update');
        $this->write('modules/Epesi/Notes/module.json', '{"installed":"from the store"}');
        $this->write('.env', 'APP_KEY=secret');
        $this->write('.htaccess', 'customised');
        $this->write('storage/logs/laravel.log', 'a log');
        $this->write('public/index.php', 'old index');
        $this->write('bootstrap/cache/packages.php', 'cached package discovery');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        @unlink($this->zip);

        parent::tearDown();
    }

    public function test_it_replaces_adds_and_removes_files_and_leaves_the_rest_alone(): void
    {
        $this->release('2.0.0RC2', [
            'VERSION' => '2.0.0RC2',
            'artisan' => 'new artisan',
            'composer.json' => '{}',
            'vendor/autoload.php' => 'new autoload',
            'vendor/same/Same.php' => 'unchanged',
            'vendor/added/New.php' => 'brand new',
            'app/Models/Thing.php' => 'new thing',
            'modules/Epesi/Mail/module.json' => '{"v":2}',
            'public/index.php' => 'new index',
            '.htaccess' => 'from the zip',
            'storage/logs/laravel.log' => 'from the zip',
        ]);

        $result = $this->updater()->apply($this->zip);

        $this->assertSame('2.0.0RC1', $result['from']);
        $this->assertSame('2.0.0RC2', $result['to']);
        $this->assertSame('2.0.0RC2', $this->read('VERSION'));
        $this->assertSame('new artisan', $this->read('artisan'));
        $this->assertSame('new autoload', $this->read('vendor/autoload.php'));
        $this->assertSame('brand new', $this->read('vendor/added/New.php'));
        $this->assertSame('new thing', $this->read('app/Models/Thing.php'));
        $this->assertSame('{"v":2}', $this->read('modules/Epesi/Mail/module.json'));

        // Dropped from the release: removed, but kept in the backup.
        $this->assertFileDoesNotExist($this->base.'/vendor/gone/Removed.php');
        $this->assertFileDoesNotExist($this->base.'/modules/Epesi/Mail/old.php');
        $this->assertSame('no longer shipped', file_get_contents($result['backup'].'/vendor/gone/Removed.php'));
        $this->assertSame('old autoload', file_get_contents($result['backup'].'/vendor/autoload.php'));

        // Package discovery is rebuilt from the new vendor/.
        $this->assertFileDoesNotExist($this->base.'/bootstrap/cache/packages.php');

        // Never touched.
        $this->assertSame('APP_KEY=secret', $this->read('.env'));
        $this->assertSame('customised', $this->read('.htaccess'));
        $this->assertSame('a log', $this->read('storage/logs/laravel.log'));
        $this->assertSame('{"installed":"from the store"}', $this->read('modules/Epesi/Notes/module.json'));

        $this->assertSame(1, $result['added']);
        $this->assertSame(6, $result['replaced']);
        $this->assertSame(2, $result['removed']);
    }

    public function test_it_refuses_an_update_that_is_not_newer(): void
    {
        $this->release('2.0.0RC1', ['VERSION' => '2.0.0RC1', 'artisan' => 'x', 'composer.json' => '{}', 'vendor/autoload.php' => 'x']);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('already at 2.0.0RC1');

        $this->updater()->apply($this->zip);
    }

    public function test_it_refuses_a_path_that_escapes_the_installation(): void
    {
        $this->release('2.0.0RC2', ['VERSION' => '2.0.0RC2', 'artisan' => 'x', 'composer.json' => '{}', 'vendor/autoload.php' => 'x', 'app/../../evil.php' => 'x']);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('unsafe path');

        $this->updater()->apply($this->zip);
    }

    public function test_it_refuses_something_a_release_does_not_contain(): void
    {
        $this->release('2.0.0RC2', ['VERSION' => '2.0.0RC2', 'artisan' => 'x', 'composer.json' => '{}', 'vendor/autoload.php' => 'x', 'secrets/keys.txt' => 'x']);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('does not');

        $this->updater()->apply($this->zip);
    }

    public function test_it_refuses_an_archive_that_does_not_match_its_manifest(): void
    {
        $this->release('2.0.0RC2', ['VERSION' => '2.0.0RC2', 'artisan' => 'x', 'composer.json' => '{}', 'vendor/autoload.php' => 'x'], extra: ['app/Unlisted.php' => 'x']);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('manifest');

        $this->updater()->apply($this->zip);
    }

    public function test_a_failure_part_way_puts_everything_back(): void
    {
        // A folder where the update wants a file makes a late step fail.
        $this->release('2.0.0RC2', [
            'VERSION' => '2.0.0RC2',
            'artisan' => 'new artisan',
            'composer.json' => '{}',
            'vendor/autoload.php' => 'new autoload',
            'app/Models/Thing.php' => 'new thing',
            'resources/views/blocked.blade.php' => 'x',
        ]);

        File::ensureDirectoryExists($this->base.'/resources/views/blocked.blade.php');

        try {
            $this->updater()->apply($this->zip);
            $this->fail('The update should have failed.');
        } catch (UpdateException $exception) {
            $this->assertStringContainsString('rolled back', $exception->getMessage());
        }

        $this->assertSame('2.0.0RC1', $this->read('VERSION'));
        $this->assertSame('old autoload', $this->read('vendor/autoload.php'));
        $this->assertSame('old thing', $this->read('app/Models/Thing.php'));
        $this->assertSame('old artisan', $this->read('artisan'));
        $this->assertSame('no longer shipped', $this->read('vendor/gone/Removed.php'));
    }

    protected function updater(): CoreUpdater
    {
        return new CoreUpdater($this->base, '2.0.0RC1', live: false);
    }

    protected function write(string $path, string $contents): void
    {
        $file = $this->base.'/'.$path;
        File::ensureDirectoryExists(dirname($file));
        file_put_contents($file, $contents);
    }

    protected function read(string $path): string
    {
        return (string) file_get_contents($this->base.'/'.$path);
    }

    /**
     * @param  array<string, string>  $files  path => contents, all listed in the manifest
     * @param  array<string, string>  $extra  in the archive but not in the manifest
     */
    protected function release(string $version, array $files, array $extra = []): void
    {
        $files['VERSION'] = $version;
        $files += ['public/index.php' => 'index'];
        $zip = new ZipArchive;
        $zip->open($this->zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $manifest = [];

        foreach ($files as $path => $contents) {
            $zip->addFromString($path, $contents);
            $manifest[] = hash('sha256', $contents).'  '.$path;
        }

        foreach ($extra as $path => $contents) {
            $zip->addFromString($path, $contents);
        }

        $zip->addFromString('update/MANIFEST.txt', implode("\n", $manifest)."\n");
        $zip->close();
    }
}
