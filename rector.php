<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/admin',
        __DIR__ . '/console',
    ])
    ->withSkip([
        __DIR__ . '/modules/Libs',
        __DIR__ . '/vendor',
    ])
    ->withPhpVersion(PhpVersion::PHP_70)
    ->withSets([
        SetList::PHP_70,
    ]);
