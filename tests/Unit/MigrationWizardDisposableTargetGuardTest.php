<?php

use Epesi\Modules\MigrationWizard\Import\DisposableTargetGuard;
use PHPUnit\Framework\TestCase;

final class MigrationWizardDisposableTargetGuardTest extends TestCase
{
    private const TOKEN = 'abc123abc123abc123abc123abc12300';

    public static function forbiddenDatabaseNames(): array
    {
        return [
            ['epesisf'],
            ['EPESISF'],
            ['epesi'],
            ['epesicrm'],
            ['production'],
            ['live'],
            [''],
        ];
    }

    /** @dataProvider forbiddenDatabaseNames */
    public function test_denylist_rejects_live_database_names(string $name): void
    {
        self::assertTrue((new DisposableTargetGuard)->isForbiddenDatabaseName($name));
    }

    public function test_denylist_allows_an_ordinary_disposable_database_name(): void
    {
        self::assertFalse((new DisposableTargetGuard)->isForbiddenDatabaseName('migration_wizard_ci_disposable'));
    }

    public function test_assert_disposable_fails_before_any_mark(): void
    {
        $target = new PDO('sqlite::memory:');
        $target->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target disposable-target token is missing or does not match.');
        (new DisposableTargetGuard)->assertDisposable($target, self::TOKEN);
    }

    public function test_mark_then_assert_disposable_succeeds_with_matching_token(): void
    {
        $target = new PDO('sqlite::memory:');
        $target->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $guard = new DisposableTargetGuard;

        $guard->markDisposable($target, self::TOKEN);
        $guard->assertDisposable($target, self::TOKEN);
        self::assertTrue(true);
    }

    public function test_assert_disposable_fails_with_a_mismatched_token(): void
    {
        $target = new PDO('sqlite::memory:');
        $target->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $guard = new DisposableTargetGuard;
        $guard->markDisposable($target, self::TOKEN);

        $otherToken = 'def456def456def456def456def45600';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target disposable-target token is missing or does not match.');
        $guard->assertDisposable($target, $otherToken);
    }

    public function test_mark_disposable_twice_with_a_different_token_is_rejected(): void
    {
        $target = new PDO('sqlite::memory:');
        $target->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $guard = new DisposableTargetGuard;
        $guard->markDisposable($target, self::TOKEN);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target is already marked disposable with a different token.');
        $guard->markDisposable($target, 'def456def456def456def456def45600');
    }
}
