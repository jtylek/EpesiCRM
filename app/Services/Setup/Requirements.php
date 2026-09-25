<?php

namespace App\Services\Setup;

use App\Support\Setup\EnvFile;

/**
 * The server check — Epesi's setup.php requirements page — shared by
 * `php artisan epesi:install` and the setup wizard's first step.
 */
class Requirements
{
    /** Extensions Laravel, Filament and the bundled modules need. */
    public const EXTENSIONS = ['ctype', 'curl', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml', 'zip'];

    /** PDO driver per connection type offered in setup. */
    public const DATABASE_DRIVERS = [
        'mysql' => 'pdo_mysql',
        'mariadb' => 'pdo_mysql',
        'pgsql' => 'pdo_pgsql',
        'sqlite' => 'pdo_sqlite',
    ];

    /**
     * @return list<array{label: string, ok: bool, status: string, required: bool}>
     */
    public function check(bool $envWritable = false): array
    {
        $rows = [];

        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $rows[] = $this->row('PHP '.PHP_VERSION, $phpOk, $phpOk ? 'OK' : 'needs 8.2 or newer');

        foreach (self::EXTENSIONS as $extension) {
            $loaded = extension_loaded($extension);
            $rows[] = $this->row("PHP extension {$extension}", $loaded, $loaded ? 'OK' : 'missing');
        }

        $drivers = array_filter(array_unique(self::DATABASE_DRIVERS), 'extension_loaded');
        $rows[] = $this->row(
            'A database driver (pdo_mysql, pdo_pgsql or pdo_sqlite)',
            $drivers !== [],
            $drivers !== [] ? implode(', ', $drivers) : 'none installed',
        );

        foreach (['storage', 'bootstrap/cache'] as $directory) {
            $writable = is_writable(base_path($directory));
            $rows[] = $this->row("{$directory}/ writable", $writable, $writable ? 'OK' : 'not writable');
        }

        if ($envWritable) {
            $writable = (new EnvFile)->writable();
            $rows[] = $this->row('.env writable', $writable, $writable ? 'OK' : 'not writable (the database settings can\'t be saved)');
        }

        // Only the GUI's module install needs this; not being able to is a
        // choice some hosts make, so it doesn't fail the check.
        $modules = is_writable(base_path('modules'));
        $rows[] = $this->row('modules/ writable', $modules, $modules ? 'OK' : 'not writable (installing modules from the web page won\'t work)', required: false);

        return $rows;
    }

    /**
     * @param  list<array{label: string, ok: bool, status: string, required: bool}>  $rows
     */
    public static function passes(array $rows): bool
    {
        foreach ($rows as $row) {
            if ($row['required'] && ! $row['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * The connection types this PHP can actually open.
     *
     * @return array<string, string>
     */
    public static function availableConnections(): array
    {
        $labels = ['mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite'];

        return array_filter($labels, fn (string $label, string $connection): bool => extension_loaded(self::DATABASE_DRIVERS[$connection]), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return array{label: string, ok: bool, status: string, required: bool}
     */
    protected function row(string $label, bool $ok, string $status, bool $required = true): array
    {
        return compact('label', 'ok', 'status', 'required');
    }
}
