<?php

namespace App\Services\LegacyImport;

/**
 * A soft time limit for one slice of a long import, so that a browser request can do a bounded piece of the
 * work and come back. Nothing sets it for `import:legacy` or the CLI, where `exceeded()` is always false.
 * An importer that can stop between two records checks it after each record (having saved where it is) and
 * throws TimeBudgetReached; the next run continues from its own checkpoint.
 */
final class TimeBudget
{
    private ?float $deadline = null;

    public function start(float $seconds): void
    {
        $this->deadline = microtime(true) + $seconds;
    }

    public function clear(): void
    {
        $this->deadline = null;
    }

    public function active(): bool
    {
        return $this->deadline !== null;
    }

    public function exceeded(): bool
    {
        return $this->deadline !== null && microtime(true) >= $this->deadline;
    }
}
