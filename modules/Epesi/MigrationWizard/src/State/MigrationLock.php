<?php

namespace Epesi\Modules\MigrationWizard\State;

use RuntimeException;

/**
 * Cross-process migration lock shared by the browser wizard, the CLI and cron,
 * so a second run can never start while one is active. Beside the state file
 * (storage/app/migration-wizard/migration.lock).
 *
 * Backed by flock(): the operating system releases it when the holding process
 * ends, even when killed, so an interrupted run never leaves it stuck and no
 * heartbeat/TTL or stale-lock takeover logic is needed. The lock file also
 * carries who holds it (owner label, pid, start time) for diagnostics only;
 * that text never decides whether the lock is held.
 */
final class MigrationLock
{
    public function __construct(private readonly string $directory) {}

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withLock(string $owner, callable $callback): mixed
    {
        $handle = $this->open();
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('Another migration run is active.');
        }
        try {
            ftruncate($handle, 0);
            fwrite($handle, json_encode([
                'owner' => preg_replace('/[^A-Za-z0-9 _.:-]/', '', $owner),
                'pid' => getmypid(),
                'started_at' => gmdate('c'),
            ], JSON_THROW_ON_ERROR));
            fflush($handle);

            return $callback();
        } finally {
            ftruncate($handle, 0);
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** True while any process holds the lock. Probes with a non-blocking shared lock, so it never disturbs a holder. */
    public function isHeld(): bool
    {
        if (! is_file($this->path())) {
            return false;
        }
        $handle = $this->open();
        try {
            if (flock($handle, LOCK_SH | LOCK_NB)) {
                flock($handle, LOCK_UN);

                return false;
            }

            return true;
        } finally {
            fclose($handle);
        }
    }

    /** Best-effort diagnostics about the holder; null when the lock is free or unreadable. */
    public function holder(): ?array
    {
        if (! $this->isHeld()) {
            return null;
        }
        $value = json_decode((string) @file_get_contents($this->path()), true);

        return is_array($value) ? $value : null;
    }

    private function path(): string
    {
        return $this->directory.'/migration.lock';
    }

    /** @return resource */
    private function open()
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Cannot create migration lock directory.');
        }
        $handle = fopen($this->path(), 'c+');
        if ($handle === false) {
            throw new RuntimeException('Cannot open migration lock file.');
        }

        return $handle;
    }
}
