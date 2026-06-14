<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/modules/Utils',
    ])
    ->withSkip([
        __DIR__ . '/modules/Libs',
        __DIR__ . '/vendor',
    ])
    ->withPhpVersion(PhpVersion::PHP_74)
    ->withSets([
        SetList::PHP_70,
        SetList::PHP_71,
        SetList::PHP_72,
        SetList::PHP_73,
        SetList::PHP_74,
    ]);
