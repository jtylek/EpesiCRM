<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/modules/CRM',
    ])
    ->withSkip([
        __DIR__ . '/modules/Libs',
        __DIR__ . '/vendor',
        __DIR__ . '/modules/CRM/Roundcube',
        __DIR__ . '/modules/Base/Theme/smarty',
        __DIR__ . '/console/Develop',
    ])
    ->withPhpVersion(PhpVersion::PHP_80)
    ->withSets([
        SetList::PHP_80,
    ]);
