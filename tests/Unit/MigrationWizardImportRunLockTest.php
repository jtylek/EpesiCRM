<?php

use Epesi\Modules\MigrationWizard\Import\ImportRunLock;
use PHPUnit\Framework\TestCase;

final class MigrationWizardImportRunLockTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $dir = tempnam(sys_get_temp_dir(), 'epesi-import-lock-');
        if ($dir === false) {
            self::fail('Unable to allocate a disposable lock directory.');
        }
        unlink($dir);
        $this->dir = $dir;
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            foreach (glob($this->dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->dir);
        }
        parent::tearDown();
    }

    public function test_sequential_runs_reuse_the_same_key_without_conflict(): void
    {
        $lock = new ImportRunLock($this->dir);
        self::assertSame('first', $lock->withLock('contacts', fn () => 'first'));
        self::assertSame('second', $lock->withLock('contacts', fn () => 'second'));
    }

    public function test_concurrent_acquisition_of_the_same_key_is_rejected(): void
    {
        $lock = new ImportRunLock($this->dir);
        $other = new ImportRunLock($this->dir);

        $lock->withLock('contacts', function () use ($other): void {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Another import run holds this lock.');
            $other->withLock('contacts', fn () => null);
        });
    }

    public function test_different_keys_do_not_conflict(): void
    {
        $lock = new ImportRunLock($this->dir);
        $other = new ImportRunLock($this->dir);

        $lock->withLock('contacts', function () use ($other): void {
            self::assertSame('ok', $other->withLock('companies', fn () => 'ok'));
        });
    }

    public function test_lock_is_released_after_the_callback_throws(): void
    {
        $lock = new ImportRunLock($this->dir);
        try {
            $lock->withLock('contacts', function (): void {
                throw new RuntimeException('simulated failure mid-run');
            });
            self::fail('Expected the simulated failure to propagate.');
        } catch (RuntimeException $e) {
            self::assertSame('simulated failure mid-run', $e->getMessage());
        }

        // The lock must not still be held after the exception unwound.
        self::assertSame('ok', $lock->withLock('contacts', fn () => 'ok'));
    }

    public function test_an_abandoned_file_handle_releases_the_lock_like_a_killed_process(): void
    {
        // Simulate a forced interruption: a lock acquired by one script/process
        // and then abruptly closed without an explicit unlock call, representing
        // the file descriptor the OS reclaims on a real process kill.
        $path = $this->dir.'/contacts.lock';
        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }
        $handle = fopen($path, 'c');
        self::assertNotFalse($handle);
        self::assertTrue(flock($handle, LOCK_EX | LOCK_NB));
        fclose($handle); // releases the flock, exactly as process exit/kill would

        $lock = new ImportRunLock($this->dir);
        self::assertSame('resumed', $lock->withLock('contacts', fn () => 'resumed'));
    }

    public function test_rejects_an_unsafe_lock_key(): void
    {
        $lock = new ImportRunLock($this->dir);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid import run lock key.');
        $lock->withLock('../escape', fn () => null);
    }
}
