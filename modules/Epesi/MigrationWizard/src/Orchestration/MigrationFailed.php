<?php

namespace Epesi\Modules\MigrationWizard\Orchestration;

use RuntimeException;

/**
 * The migration failed and cannot continue: a failure after the wizard began to change things that is
 * not known to be safe to continue from (verification failure, data problems, anything unexpected). The
 * reason is recorded in the state, and every later run shows the same message. The remedy is to restore
 * the pre-upgrade backup; the wizard has no way back of its own.
 */
final class MigrationFailed extends RuntimeException
{
    public function __construct(public readonly string $reason, ?\Throwable $previous = null)
    {
        parent::__construct(self::message($reason), 0, $previous);
    }

    public static function message(string $reason): string
    {
        return "Migration failed and cannot continue.\n"
            ."Reason: {$reason} (copy this before restoring)\n"
            .'Please restore your Epesi directory and database from your backup, then switch PHP back to 7.4. See the Upgrade Guide.';
    }
}
