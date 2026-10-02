<?php

namespace App\Support\Optimize;

/**
 * The php.ini settings recommended for a production server, and how this
 * server's compare (AI-shared/Epesi-optimization.md). php-production.ini in
 * the epesi folder holds the same values, ready to copy into php.ini;
 * OptimizeTest keeps the two in step.
 *
 * Most of them only php.ini itself can set: OPcache's sizes are fixed when
 * PHP starts, so neither .htaccess, .user.ini nor ini_set() can change them,
 * and on shared hosting only the host can. Shown on Administration → About
 * and, below the recommendation, as warnings in the setup wizard's server
 * check. Read in the web server's PHP: the command line's php.ini may differ.
 */
class PhpSettings
{
    /**
     * setting => [recommended value, how to compare]
     *
     * Comparisons: "on"/"off" for a switch, "min" for a number or size that
     * may be larger (memory_limit -1, unlimited, counts as larger), "same"
     * for an exact value. Why each one matters: reason().
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const RECOMMENDED = [
        'opcache.enable' => ['1', 'on'],
        'opcache.memory_consumption' => ['256', 'min'],
        'opcache.interned_strings_buffer' => ['32', 'min'],
        'opcache.max_accelerated_files' => ['20000', 'min'],
        'opcache.validate_timestamps' => ['1', 'on'],
        'realpath_cache_size' => ['4096K', 'min'],
        'realpath_cache_ttl' => ['600', 'min'],
        'memory_limit' => ['256M', 'min'],
        'zend.assertions' => ['-1', 'same'],
        'display_errors' => ['Off', 'off'],
        'expose_php' => ['Off', 'off'],
    ];

    /** Why a setting matters, for Administration → About. */
    public static function reason(string $setting): string
    {
        return match ($setting) {
            'opcache.enable' => __('Keeps compiled PHP in memory. Without it, every request compiles epesi\'s PHP files again.'),
            'opcache.memory_consumption' => __('Megabytes for compiled PHP. epesi alone needs about 50, and every other site on the server more.'),
            'opcache.interned_strings_buffer' => __('Megabytes for the strings compiled files share.'),
            'opcache.max_accelerated_files' => __('epesi has about 15,000 PHP files, compiled views included.'),
            'opcache.validate_timestamps' => __('Must stay on: cron rebuilds epesi\'s caches, and the web server has to notice the new files.'),
            'realpath_cache_size' => __('Remembers where files are, instead of looking each one up on every request.'),
            'realpath_cache_ttl' => __('Seconds to remember them.'),
            'memory_limit' => __('Imports, long lists and setup need more than the usual 128M.'),
            'zend.assertions' => __('Leaves assertion code out of compiled files. Only php.ini can set it.'),
            'display_errors' => __('Error details belong in the log, not on the page.'),
            'expose_php' => __('Doesn\'t announce the PHP version in every response.'),
            default => '',
        };
    }

    /**
     * Which PHP this is: the version, how it runs (the web server's module,
     * PHP-FPM, LiteSpeed…) and the php.ini it loaded, which is the file to
     * edit. Empty php.ini when PHP loaded none.
     *
     * @return array{version: string, sapi: string, ini: string, supported: bool}
     */
    public static function runtime(): array
    {
        return [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'ini' => (string) php_ini_loaded_file(),
            'supported' => version_compare(PHP_VERSION, '8.2.0', '>='),
        ];
    }

    /**
     * Every recommended setting with this server's value.
     *
     * @return list<array{setting: string, current: string, recommended: string, ok: bool, why: string}>
     */
    public static function compare(): array
    {
        $rows = [];

        foreach (self::RECOMMENDED as $setting => [$recommended, $how]) {
            $current = static::configured($setting);

            $rows[] = [
                'setting' => $setting,
                'current' => static::display($setting, $current),
                'recommended' => static::display($setting, $recommended),
                'ok' => $current !== false && static::meets((string) $current, $recommended, $how),
                'why' => static::reason($setting),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{setting: string, current: string, recommended: string, ok: bool, why: string}>
     */
    public static function belowRecommendation(): array
    {
        return array_values(array_filter(static::compare(), fn (array $row): bool => ! $row['ok']));
    }

    public static function meets(string $current, string $recommended, string $how): bool
    {
        return match ($how) {
            'on' => static::isOn($current),
            'off' => ! static::isOn($current),
            'same' => trim($current) === $recommended,
            'min' => static::quantity($current) === -1 || static::quantity($current) >= static::quantity($recommended),
        };
    }

    /**
     * The value php.ini gives. For display_errors that isn't ini_get():
     * Laravel switches it off at runtime outside tests, which says nothing
     * about the errors PHP shows before Laravel starts. PHP's own default,
     * when php.ini doesn't set it, is on.
     */
    protected static function configured(string $setting): string|false
    {
        if ($setting === 'display_errors') {
            $configured = get_cfg_var('display_errors');

            return $configured === false ? '1' : (string) $configured;
        }

        return ini_get($setting);
    }

    protected static function isOn(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'on', 'yes', 'true', 'stdout', 'stderr'], true);
    }

    /** "256M" → bytes, "20000" → 20000, -1 stays -1. */
    protected static function quantity(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return $value === '' ? 0 : -1;
        }

        try {
            return (int) @ini_parse_quantity($value);
        } catch (\Throwable) {
            return (int) $value;
        }
    }

    protected static function display(string $setting, string|false $current): string
    {
        if ($current === false) {
            return str_starts_with($setting, 'opcache.') ? __('OPcache not installed') : __('unknown');
        }

        return match (true) {
            $current === '' => __('off'),
            in_array($setting, ['opcache.enable', 'opcache.validate_timestamps', 'display_errors', 'expose_php'], true) => static::isOn($current) ? __('on') : __('off'),
            default => $current,
        };
    }
}
