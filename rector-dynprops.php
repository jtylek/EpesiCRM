<?php

declare(strict_types=1);

// Scope-assessment config: ONLY the dynamic-properties rule. Dry-run this to see how many classes
// assign undeclared (dynamic) properties — deprecated in PHP 8.2 (E_DEPRECATED, suppressed; error
// in 9.0). The rule would add #[\AllowDynamicProperties] to each such class. It is intentionally
// NOT in SetList::PHP_82 (opinionated). Use the count to decide: attribute on base classes only,
// or defer. Run:  rector process --dry-run --config rector-dynprops.php

use Rector\Config\RectorConfig;
use Rector\Php82\Rector\Class_\AddAllowDynamicPropertiesAttributeRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/include',
        __DIR__ . '/modules',
    ])
    ->withSkip([
        __DIR__ . '/modules/Libs',
        __DIR__ . '/vendor',
        __DIR__ . '/modules/CRM/Roundcube',
        __DIR__ . '/modules/Base/Theme/smarty',
        __DIR__ . '/modules/Tests',
    ])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withRules([
        AddAllowDynamicPropertiesAttributeRector::class,
    ]);
