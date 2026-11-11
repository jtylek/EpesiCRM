<?php

namespace Epesi\Modules\MigrationWizard\Import;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Hard gate against importing into a live or unmarked database.
 *
 * A target connection must be explicitly marked disposable with an opaque,
 * caller-supplied token before ImportExecutor will write to it. A known
 * live/production database name (never `epesisf`, among others) is refused
 * even when marked, so a mistaken token cannot bypass the denylist.
 */
final class DisposableTargetGuard
{
    private const FORBIDDEN_DATABASE_NAMES = ['epesisf', 'epesi', 'epesicrm', 'production', 'live'];

    public function markDisposable(PDO $target, string $token): void
    {
        $this->assertValidToken($token);
        $this->assertNotForbiddenDatabase($target);
        $target->exec('CREATE TABLE IF NOT EXISTS epesi_migration_disposable_target (
            id INTEGER PRIMARY KEY, token TEXT NOT NULL)');
        $existing = $target->query('SELECT token FROM epesi_migration_disposable_target WHERE id = 1')
            ->fetch(PDO::FETCH_ASSOC);
        if ($existing !== false && $existing['token'] !== $token) {
            throw new RuntimeException('Target is already marked disposable with a different token.');
        }
        if ($existing === false) {
            $target->prepare('INSERT INTO epesi_migration_disposable_target (id, token) VALUES (1, :token)')
                ->execute([':token' => $token]);
        }
    }

    public function assertDisposable(PDO $target, string $token): void
    {
        $this->assertValidToken($token);
        $this->assertNotForbiddenDatabase($target);
        try {
            $statement = $target->query('SELECT token FROM epesi_migration_disposable_target WHERE id = 1');
            $row = $statement !== false ? $statement->fetch(PDO::FETCH_ASSOC) : false;
        } catch (Throwable) {
            $row = false;
        }
        if ($row === false || ! is_string($row['token'] ?? null) || ! hash_equals($row['token'], $token)) {
            throw new RuntimeException('Target disposable-target token is missing or does not match.');
        }
    }

    /** Pure, injectable so the denylist is unit-testable without a live database connection. */
    public function isForbiddenDatabaseName(string $name): bool
    {
        return $name === '' || in_array(strtolower($name), self::FORBIDDEN_DATABASE_NAMES, true);
    }

    private function assertValidToken(string $token): void
    {
        if (! preg_match('/^[a-f0-9]{32,64}$/D', $token)) {
            throw new RuntimeException('Invalid disposable-target token.');
        }
    }

    private function assertNotForbiddenDatabase(PDO $target): void
    {
        if ($target->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            // Non-mysql fixtures (SQLite file/:memory:) carry no shared live database name.
            return;
        }
        $name = (string) $target->query('SELECT DATABASE()')->fetchColumn();
        if ($this->isForbiddenDatabaseName($name)) {
            throw new RuntimeException('Refusing to use a protected or unnamed database as an import target.');
        }
    }
}
