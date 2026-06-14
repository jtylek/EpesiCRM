<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/include',
    ])
    ->withPhpVersion(PhpVersion::PHP_70)
    ->withSets([
        SetList::PHP_70,
    ]);
