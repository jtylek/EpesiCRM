<?php

use Epesi\Modules\MigrationWizard\State\MigrationLock;
use PHPUnit\Framework\TestCase;

final class MigrationWizardMigrationLockTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/epesi-migration-lock-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_runs_sequentially_and_releases_after_each_run(): void
    {
        $lock = new MigrationLock($this->dir);
        self::assertFalse($lock->isHeld());
        self::assertSame('a', $lock->withLock('wizard', fn () => 'a'));
        self::assertFalse($lock->isHeld());
        self::assertSame('b', $lock->withLock('cli', fn () => 'b'));
    }

    public function test_a_second_run_cannot_start_while_one_is_active(): void
    {
        $lock = new MigrationLock($this->dir);
        $other = new MigrationLock($this->dir);
        $lock->withLock('wizard', function () use ($lock, $other): void {
            self::assertTrue($other->isHeld());
            self::assertSame('wizard', $other->holder()['owner']);
            try {
                $other->withLock('cron', fn () => self::fail('must not run'));
                self::fail('second run was admitted');
            } catch (RuntimeException $e) {
                self::assertSame('Another migration run is active.', $e->getMessage());
            }
            self::assertTrue($lock->isHeld());
        });
        self::assertFalse($other->isHeld());
        self::assertNull($other->holder());
    }

    public function test_the_lock_is_released_when_the_run_throws(): void
    {
        $lock = new MigrationLock($this->dir);
        try {
            $lock->withLock('wizard', function (): void {
                throw new LogicException('boom');
            });
        } catch (LogicException) {
        }
        self::assertFalse($lock->isHeld());
        self::assertSame('ok', $lock->withLock('wizard', fn () => 'ok'));
    }

    public function test_owner_label_is_sanitised(): void
    {
        $lock = new MigrationLock($this->dir);
        $lock->withLock("evil\n\"owner\"<script>", function () use ($lock): void {
            self::assertSame('evilownerscript', $lock->holder()['owner']);
        });
    }
}
