<?php

use Epesi\Modules\MigrationWizard\Cleanup\CleanupManifests;
use PHPUnit\Framework\TestCase;

final class MigrationWizardCleanupManifestsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/epesi-manifests-'.bin2hex(random_bytes(6));
        mkdir($this->root.'/update', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/update/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->root.'/update');
        @rmdir($this->root);
        parent::tearDown();
    }

    /** @param  list<string>  $paths */
    private function write(array $paths, bool $pad = true): void
    {
        if ($pad) {
            for ($i = 0; $i < 250; $i++) {
                $paths[] = "vendor/package/File{$i}.php";
            }
        }
        $lines = array_map(static fn (string $path): string => hash('sha256', $path).'  '.$path, $paths);
        file_put_contents($this->root.'/'.CleanupManifests::RELEASE_FILE, implode("\n", $lines)."\n");
    }

    private function shipped(array $extra = []): array
    {
        return [...CleanupManifests::MUST_SHIP, ...$extra];
    }

    public function test_nothing_is_loaded_when_the_release_list_is_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing or unreadable');
        (new CleanupManifests)->load($this->root);
    }

    public function test_the_release_list_is_read_from_the_update_manifest_the_packager_writes(): void
    {
        $this->write($this->shipped(['app/A.php']));

        $loaded = (new CleanupManifests)->load($this->root);

        self::assertContains('app/A.php', $loaded['release']);
        self::assertContains('artisan', $loaded['release']);
        self::assertContains('vendor/package/File7.php', $loaded['release']);
        self::assertSame(['release'], array_keys($loaded), 'there is no legacy list any more');
    }

    public function test_a_list_that_does_not_ship_the_application_is_refused(): void
    {
        $this->write(['app/A.php', 'composer.json']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not name');
        (new CleanupManifests)->load($this->root);
    }

    public function test_an_implausibly_short_list_is_refused_so_that_everything_is_never_removable(): void
    {
        $this->write($this->shipped(), pad: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('implausibly short');
        (new CleanupManifests)->load($this->root);
    }

    public function test_malformed_lists_and_lists_that_name_installation_data_are_refused(): void
    {
        foreach (["not a checksum line\n", hash('sha256', 'x')."  ../outside\n", hash('sha256', 'x')."  data/config.php\n", hash('sha256', 'x')."  .env\n"] as $bad) {
            file_put_contents($this->root.'/'.CleanupManifests::RELEASE_FILE, $bad);
            try {
                (new CleanupManifests)->load($this->root);
                self::fail('A bad list was accepted: '.trim($bad));
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_a_linked_list_is_refused(): void
    {
        $this->write($this->shipped());
        $copy = $this->root.'/elsewhere.txt';
        rename($this->root.'/'.CleanupManifests::RELEASE_FILE, $copy);
        if (! @symlink($copy, $this->root.'/'.CleanupManifests::RELEASE_FILE)) {
            @unlink($copy);
            self::markTestSkipped('Symbolic links cannot be created here.');
        }
        try {
            (new CleanupManifests)->load($this->root);
            self::fail('A linked list was accepted.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('missing or unreadable', $e->getMessage());
        } finally {
            @unlink($this->root.'/'.CleanupManifests::RELEASE_FILE);
            @unlink($copy);
        }
    }
}
