<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/admin',
        __DIR__ . '/console',
        __DIR__ . '/modules/FirstRun',
        __DIR__ . '/modules/Data',
        __DIR__ . '/modules/Tools',
        __DIR__ . '/modules/Apps',
        __DIR__ . '/modules/Applets',
        __DIR__ . '/ajax.php',
        __DIR__ . '/check.php',
        __DIR__ . '/console.php',
        __DIR__ . '/cron.php',
        __DIR__ . '/debug.php',
        __DIR__ . '/include.php',
        __DIR__ . '/index.php',
        __DIR__ . '/init_js.php',
        __DIR__ . '/mobile.php',
        __DIR__ . '/monitoring.php',
        __DIR__ . '/process.php',
        __DIR__ . '/serve.php',
        __DIR__ . '/setup.php',
        __DIR__ . '/update.php',
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
