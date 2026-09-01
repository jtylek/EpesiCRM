<?php

declare(strict_types=1);

// CI-only Rector config: dry-run the PHP 8.3 rule set to surface 8.3-level changes the core
// migration (rector.php, PHP_81) didn't target. Bumped from the PHP_82 set 2026-09-01 once that
// sweep reported clean (636 files, zero changes — see MIGRATION_NOTES.md). Advisory only; we
// review the diff and apply in batches. The runtime config is rector.php (PHP_81); this one is
// for the hardening sweep.
//
// The six rules below ARE Rector's PHP 8.3 set, listed by hand. `SetList::PHP_83` would be the
// short way to say this, but Rector 2.6 deprecates the per-version set files it resolves to
// ("The per-version PHP set php83.php is deprecated. Use withPhpSets() or withPhpLevel()").
//
// Do NOT take that advice literally here: `withPhpSets(php83: true)` is *cumulative* - "all rules
// up to this version" - so it also pulls in every set from 5.3 upward. Measured 2026-09-01: it
// reports 508 files, almost all LongArrayToShortArrayRector rewriting `array()` to `[]` across a
// codebase that uses `array()` by convention. That is a wholesale restyling, not an 8.3 sweep, and
// it is exactly what CLAUDE.md's "surgical, convention-matching changes" rule says not to do.
// `withPhpSets()` with no argument is worse still: it reads the PHP floor from composer.json, which
// declares no `php` constraint, and fails outright.
//
// Listing the rules keeps the sweep to genuinely-8.3 concerns and makes it obvious what is checked.
// Re-sync from vendor/rector/rector/config/set/php83.php after a Rector upgrade.
//
// WARNING - two of these EMIT 8.3-only syntax, so applying them would raise the language floor
// from 8.1 (MIGRATION_NOTES.md §85) to 8.3: AddTypeToConstRector writes typed class constants, and
// ReadOnlyAnonymousClassRector writes `new readonly class`. That is a release decision, not a
// cleanup - which is a large part of why this config is advisory and never blocking.

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\BooleanAnd\JsonValidateRector;
use Rector\Php83\Rector\Class_\ReadOnlyAnonymousClassRector;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;
use Rector\Php83\Rector\FuncCall\CombineHostPortLdapUriRector;
use Rector\Php83\Rector\FuncCall\DynamicClassConstFetchRector;
use Rector\Php83\Rector\FuncCall\RemoveGetClassGetParentClassNoArgsRector;
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
    ->withRules([
        AddTypeToConstRector::class,
        CombineHostPortLdapUriRector::class,
        RemoveGetClassGetParentClassNoArgsRector::class,
        ReadOnlyAnonymousClassRector::class,
        DynamicClassConstFetchRector::class,
        JsonValidateRector::class,
    ]);
