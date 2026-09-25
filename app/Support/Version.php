<?php

namespace App\Support;

/**
 * The epesi version, from the VERSION file at the top of the application:
 * the one place a release sets it. The full form (2.0.0) is what module
 * manifests' "epesi_core" constraints are checked against; people see the
 * short form (epesi 2.0), without a patch number of 0.
 */
class Version
{
    protected static ?string $current = null;

    /** e.g. "2.0.0" */
    public static function current(): string
    {
        return static::$current ??= static::read();
    }

    /** e.g. "2.0" for 2.0.0, "2.0.1" for 2.0.1 */
    public static function short(): string
    {
        return preg_replace('/^(\d+\.\d+)\.0$/', '$1', static::current());
    }

    /** e.g. "epesi 2.0" */
    public static function label(): string
    {
        return 'epesi '.static::short();
    }

    protected static function read(): string
    {
        $file = base_path('VERSION');
        $version = is_file($file) ? trim((string) file_get_contents($file)) : '';

        return $version !== '' ? $version : '0.0.0';
    }
}
