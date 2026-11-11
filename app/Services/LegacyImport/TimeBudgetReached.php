<?php

namespace App\Services\LegacyImport;

use RuntimeException;

/** The slice's time budget ran out between two records. Nothing is wrong: the run continues from its checkpoint. */
final class TimeBudgetReached extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The time budget of this step is used up; continue to go on.');
    }
}
