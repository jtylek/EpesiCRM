<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/modules/FirstRun',
        __DIR__ . '/modules/Data',
        __DIR__ . '/modules/Tools',
        __DIR__ . '/modules/Apps',
        __DIR__ . '/modules/Applets',
    ])
    ->withSkip([
        __DIR__ . '/modules/Libs',
        __DIR__ . '/vendor',
    ])
    ->withPhpVersion(PhpVersion::PHP_70)
    ->withSets([
        SetList::PHP_70,
    ]);
