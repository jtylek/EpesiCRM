<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/include',
        __DIR__ . '/admin',
        __DIR__ . '/console',
        __DIR__ . '/modules',
        __DIR__ . '/ajax.php',
        __DIR__ . '/check.php',
        __DIR__ . '/console.php',
        __DIR__ . '/cron.php',
        __DIR__ . '/debug.php',
        __DIR__ . '/include.php',
        __DIR__ . '/index.php',
        __DIR__ . '/init_js.php',
        __DIR__ . '/monitoring.php',
        __DIR__ . '/process.php',
        __DIR__ . '/serve.php',
        __DIR__ . '/setup.php',
        __DIR__ . '/update.php',
    ])
    ->withSkip([
        __DIR__ . '/modules/Libs',
        __DIR__ . '/vendor',
        __DIR__ . '/modules/Base/Theme/smarty',
        __DIR__ . '/console/Develop',
        __DIR__ . '/modules/Tests',
        // Separately-licensed, gitignored nested git repos - not ours to rewrite, and CI
        // checks out neither. Mirrors phpstan.neon's excludePaths. See CLAUDE.md.
        __DIR__ . '/modules/Premium',
        __DIR__ . '/modules/Custom',
        // skip globally: mass null→(string) casting is a PHP 9 prep, not a 8.2 target;
        // 199 files of mostly-unnecessary casts. Left for Jasiek to review properly.
        NullToStrictStringFuncCallArgRector::class,
    ])
    ->withPhpVersion(PhpVersion::PHP_81)
    ->withSets([
        SetList::PHP_81,
    ]);
