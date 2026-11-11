<?php

namespace Epesi\Modules\MigrationWizard\Import;

use RuntimeException;

/**
 * Process-held exclusive lock for one import run, backed by an advisory
 * filesystem lock (flock). Deliberately not a heartbeat/TTL lock: the
 * operating system releases flock automatically when the holding process's
 * file descriptors close, including on a forced kill, so a run interrupted
 * mid-batch never leaves a run lock stuck for the next attempt.
 */
final class ImportRunLock
{
    public function __construct(private readonly string $directory) {}

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function withLock(string $key, callable $callback): mixed
    {
        if ($key === '' || ! preg_match('/^[a-zA-Z0-9_-]+$/D', $key)) {
            throw new RuntimeException('Invalid import run lock key.');
        }
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Cannot create import run lock directory.');
        }
        $path = $this->directory.'/'.$key.'.lock';
        $handle = fopen($path, 'c');
        if ($handle === false) {
            throw new RuntimeException('Cannot open import run lock file.');
        }
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('Another import run holds this lock.');
        }
        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
