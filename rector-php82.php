<?php

declare(strict_types=1);

// CI-only Rector config: dry-run the PHP 8.2 rule set to surface 8.2-level changes the core
// migration (rector.php, PHP_81) didn't target — chiefly dynamic-property classes (8.2 deprecation
// → #[\AllowDynamicProperties] / declared props). Advisory only; we review the diff and apply in
// batches. The runtime config is rector.php (PHP_81); this one is for the hardening sweep.

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/include',
        __DIR__ . '/modules',
    ])
    ->withSkip([
        __DIR__ . '/modules/Libs',
        __DIR__ . '/vendor',
        __DIR__ . '/modules/Base/Theme/smarty',
        __DIR__ . '/modules/Tests',
    ])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withSets([
        SetList::PHP_82,
    ]);
