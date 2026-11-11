<?php

namespace Epesi\Modules\MigrationWizard\Orchestration;

use RuntimeException;

/**
 * The run stopped on an interruption or a problem the administrator can fix (no disk space, a timeout, a
 * lost connection, another run holding the lock, a missing confirmation). Nothing is lost: the steps are
 * idempotent, and running the migration again with --resume continues safely.
 */
final class MigrationStopped extends RuntimeException
{
    public function __construct(public readonly string $reason, ?\Throwable $previous = null)
    {
        parent::__construct(self::message($reason), 0, $previous);
    }

    public static function message(string $reason): string
    {
        return "Migration stopped. Fix the problem and run it again to continue.\nReason: {$reason}";
    }

    /** Whether a failure looks like an interruption an administrator can fix (never a data problem). */
    public static function isInterruption(\Throwable $exception): bool
    {
        for ($e = $exception; $e !== null; $e = $e->getPrevious()) {
            if (preg_match('/no space left|disk full|not enough (disk )?space|quota exceeded|could not write|failed to write|server has gone away|lost connection|connection (refused|reset|timed out)|timed out|maximum execution time|lock wait timeout|deadlock found|another migration run is active/i', $e->getMessage()) === 1) {
                return true;
            }
        }

        return false;
    }
}
