<?php

// What epesi needs from PHP. Read both by bootstrap/preflight.php (before
// anything else loads, on any PHP version) and by the setup wizard's server
// check (App\Services\Setup\Requirements), so keep it plain PHP 5 syntax.

return array(
    'php' => '8.2.0',

    // Extensions Laravel, Filament and the bundled modules need.
    'extensions' => array('ctype', 'curl', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml', 'zip'),

    // PDO driver per connection type offered in setup; one of them is enough.
    'database_drivers' => array(
        'mysql' => 'pdo_mysql',
        'mariadb' => 'pdo_mysql',
        'pgsql' => 'pdo_pgsql',
        'sqlite' => 'pdo_sqlite',
    ),
);
