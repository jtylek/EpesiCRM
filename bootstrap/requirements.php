<?php

// What epesi needs from PHP. Read both by bootstrap/preflight.php (before
// anything else loads, on any PHP version) and by the setup wizard's server
// check (App\Services\Setup\Requirements), so keep it plain PHP 5 syntax.

return array(
    // 8.3 since 3.0 (2026-10-16): no openspout release (Filament's export
    // dependency) supports both 8.2 and 8.5. Keep composer.json's "php" and
    // config.platform.php in step with it.
    'php' => '8.3.0',

    // The newest PHP branch epesi is tested on. A newer one isn't refused,
    // only flagged as not tested yet. Raise it once the suite passes there.
    'php_tested' => '8.5',

    // Extensions Laravel, Filament and the bundled modules need.
    'extensions' => array('ctype', 'curl', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml', 'zip'),

    // PDO driver per connection type offered in setup; one of them is enough.
    'database_drivers' => array(
        'mysql' => 'pdo_mysql',
        'mariadb' => 'pdo_mysql',
    ),
);
