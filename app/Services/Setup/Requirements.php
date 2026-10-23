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
     * @return array{php: string, php_tested: string, extensions: list<string>, database_drivers: array<string, string>}
     */
    public static function definition(): array
    {
        static $definition;

        return $definition ??= require dirname(__DIR__, 3).'/bootstrap/requirements.php';
    }

    /** The lowest PHP version epesi runs on, short form: "8.3". */
    public static function minimumPhp(): string
    {
        return preg_replace('/\.0$/', '', self::definition()['php']);
    }

    /** The newest PHP branch epesi is tested on: "8.5". */
    public static function testedPhp(): string
    {
        return self::definition()['php_tested'];
    }

    public static function phpSupported(): bool
    {
        return version_compare(PHP_VERSION, self::definition()['php'], '>=');
    }

    /** False on a PHP branch newer than the tested one (8.6 when 8.5 is the newest tested). */
    public static function phpTested(): bool
    {
        return version_compare(PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION, self::testedPhp(), '<=');
    }

    /**
     * The PHP version check, shared by check() (the setup wizard, epesi:install
     * and Server Check's requirements) and Server Check's "PHP version" row.
     * bootstrap/preflight.php reads the same bootstrap/requirements.php itself,
     * since it runs before the autoloader.
     *
     * @return array{version: string, minimum: string, tested_up_to: string, range: string, supported: bool, tested: bool, status: string}
     */
    public static function php(): array
    {
        $supported = self::phpSupported();
        $tested = self::phpTested();

        return [
            'version' => PHP_VERSION,
            'minimum' => self::minimumPhp(),
            'tested_up_to' => self::testedPhp(),
            // An en dash, not the word "to": the range goes into translated
            // sentences ("epesi runs on :php"), and machine translation
            // scrambles a sentence whose placeholders are the two ends. No full stop
            // either: DeepL put it in the wrong place in Persian and Japanese.
            'range' => self::minimumPhp()."\u{2013}".self::testedPhp(),
            'supported' => $supported,
            'tested' => $tested,
            'status' => match (true) {
                ! $supported => 'needs '.self::minimumPhp().' or newer',
                ! $tested => 'not tested yet (epesi is tested up to PHP '.self::testedPhp().')',
                default => 'OK',
            },
        ];
    }

    /**
     * @return list<array{label: string, ok: bool, status: string, required: bool}>
     */
    public function check(bool $envWritable = false, bool $phpIni = true): array
    {
        $rows = [];
        $php = self::php();
        $rows[] = $this->row('PHP '.$php['version'].' (supported '.$php['range'].')', $php['supported'], $php['supported'] ? 'OK' : $php['status']);

        if ($php['supported'] && ! $php['tested']) {
            $rows[] = $this->row('PHP '.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.' tested', false, $php['status'], required: false);
        }

        foreach (self::definition()['extensions'] as $extension) {
            $loaded = extension_loaded($extension);
            $rows[] = $this->row("PHP extension {$extension}", $loaded, $loaded ? 'OK' : 'missing');
        }

        $drivers = array_filter(array_unique(self::definition()['database_drivers']), 'extension_loaded');
        $rows[] = $this->row(
            'A database driver (pdo_mysql)',
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

        // Installing Roundcube writes its web entry, public/epesi-webmail; as the
        // web server user that fails with "Permission denied" when public/
        // isn't writable. Optional: Roundcube is a module.
        $public = is_writable(public_path());
        $rows[] = $this->row('public/ writable', $public, $public ? 'OK' : 'not writable (installing Roundcube from the web page won\'t work)', required: false);

        // The Roundcube download and cron's tasks need these; a panel such as
        // aaPanel or a shared host often disables them. Only in the browser,
        // since the command line's php.ini isn't the web server's.
        if ($phpIni && ! app()->runningInConsole()) {
            $blocked = self::disabledFunctions(['exec', 'proc_open', 'putenv', 'symlink']);

            $rows[] = $this->row(
                'PHP functions exec, proc_open, putenv, symlink',
                $blocked === [],
                $blocked === [] ? 'OK' : __('disabled: :functions (remove them from "disable_functions" in php.ini or the control panel; Roundcube needs them)', ['functions' => implode(', ', $blocked)]),
                required: false,
            );
        }

        // Speed, not a requirement: warnings only.
        if ($phpIni && ! app()->runningInConsole()) {
            $below = PhpSettings::belowRecommendation();

            foreach ($below as $setting) {
                $rows[] = $this->row(
                    "php.ini {$setting['setting']}",
                    false,
                    __(':current, :recommended recommended (see config/php-production.ini)', ['current' => $setting['current'], 'recommended' => $setting['recommended']]),
                    required: false,
                );
            }

            if ($below === []) {
                $rows[] = $this->row('php.ini settings for speed', true, 'OK');
            }
        }

        // Cron's PHP can be another binary than this one, so this page can't see what it
        // lacks: cron.php's preflight leaves a note when it could not start, and takes it
        // away at its next clean run.
        if ($note = self::cronNote()) {
            $rows[] = $this->row(
                'PHP that runs cron',
                false,
                // Plain English like the other statuses here: this list is part of the setup, whose
                // every __() string has to be in every language.
                sprintf(
                    'cron could not start at %s with %s (PHP %s): %s. Fix that PHP; the next cron run clears this',
                    $note['time'],
                    $note['binary'],
                    $note['php'],
                    implode('; ', $note['problems']),
                ),
            );
        }

        return $rows;
    }

    /**
     * What cron.php's preflight found wrong with the PHP that runs cron, if its last
     * start failed (see bootstrap/preflight.php).
     *
     * @return array{time: string, php: string, binary: string, problems: list<string>}|null
     */
    public static function cronNote(): ?array
    {
        $path = storage_path('framework/cron-preflight.json');
        $note = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($note) || ! is_array($note['problems'] ?? null) || $note['problems'] === []) {
            return null;
        }

        return [
            'time' => (string) ($note['time'] ?? ''),
            'php' => (string) ($note['php'] ?? ''),
            'binary' => (string) ($note['binary'] ?? ''),
            'problems' => array_map('strval', $note['problems']),
        ];
    }

    /**
     * Which of these functions this PHP has switched off (disable_functions).
     *
     * @param  list<string>  $functions
     * @return list<string>
     */
    public static function disabledFunctions(array $functions): array
    {
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

        return array_values(array_filter($functions, fn (string $function): bool => in_array($function, $disabled, true) || ! function_exists($function)));
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
        $labels = ['mysql' => 'MySQL', 'mariadb' => 'MariaDB'];

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
