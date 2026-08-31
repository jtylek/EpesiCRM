<?php

declare(strict_types=1);

// CI-only Rector config: dry-run the PHP 8.3 rule set to surface 8.3-level changes the core
// migration (rector.php, PHP_81) didn't target. Bumped from the PHP_82 set 2026-09-01 once that
// sweep reported clean (636 files, zero changes — see MIGRATION_NOTES.md). Advisory only; we
// review the diff and apply in batches. The runtime config is rector.php (PHP_81); this one is
// for the hardening sweep.

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
        // Separately-licensed, gitignored nested git repos - not ours to rewrite, and CI
        // checks out neither. Mirrors phpstan.neon's excludePaths. See CLAUDE.md.
        __DIR__ . '/modules/Premium',
        __DIR__ . '/modules/Custom',
    ])
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withSets([
        SetList::PHP_83,
    ]);
