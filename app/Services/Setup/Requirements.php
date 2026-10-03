<?php

namespace App\Services\Setup;

use App\Support\Optimize\PhpSettings;
use App\Support\Setup\EnvFile;

/**
 * The server check — Epesi's setup.php requirements page — shared by
 * `php artisan epesi:install` and the setup wizard's first step.
 */
class Requirements
{
    /**
     * The PHP version and extensions, shared with bootstrap/preflight.php,
     * which checks them before Laravel loads.
     *
     * @return array{php: string, extensions: list<string>, database_drivers: array<string, string>}
     */
    public static function definition(): array
    {
        static $definition;

        return $definition ??= require dirname(__DIR__, 3).'/bootstrap/requirements.php';
    }

    /**
     * @return list<array{label: string, ok: bool, status: string, required: bool}>
     */
    public function check(bool $envWritable = false): array
    {
        $rows = [];
        $minimum = self::definition()['php'];

        $phpOk = version_compare(PHP_VERSION, $minimum, '>=');
        $rows[] = $this->row('PHP '.PHP_VERSION, $phpOk, $phpOk ? 'OK' : 'needs '.preg_replace('/\.0$/', '', $minimum).' or newer');

        foreach (self::definition()['extensions'] as $extension) {
            $loaded = extension_loaded($extension);
            $rows[] = $this->row("PHP extension {$extension}", $loaded, $loaded ? 'OK' : 'missing');
        }

        $drivers = array_filter(array_unique(self::definition()['database_drivers']), 'extension_loaded');
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

        // Speed, not a requirement: warnings only. Only in the browser, since
        // the command line's php.ini isn't the web server's.
        if (! app()->runningInConsole()) {
            $below = PhpSettings::belowRecommendation();

            foreach ($below as $setting) {
                $rows[] = $this->row(
                    "php.ini {$setting['setting']}",
                    false,
                    __(':current, :recommended recommended (see php-production.ini)', ['current' => $setting['current'], 'recommended' => $setting['recommended']]),
                    required: false,
                );
            }

            if ($below === []) {
                $rows[] = $this->row('php.ini settings for speed', true, 'OK');
            }
        }

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

        return array_filter($labels, fn (string $label, string $connection): bool => extension_loaded(self::definition()['database_drivers'][$connection]), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return array{label: string, ok: bool, status: string, required: bool}
     */
    protected function row(string $label, bool $ok, string $status, bool $required = true): array
    {
        return compact('label', 'ok', 'status', 'required');
    }
}
