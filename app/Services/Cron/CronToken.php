<?php

namespace App\Services\Cron;

use Illuminate\Support\Facades\File;

/**
 * The secret in the cron URL — Epesi's Base_CronCommon token. Whoever has the
 * URL can start cron, so it is long and random, compared in constant time,
 * and replaced from Administration → Cron when it may have leaked. It lives
 * in a file on the server (config('cron.token_path')), created on first use.
 */
class CronToken
{
    public static function current(): string
    {
        $path = static::path();

        if (is_file($path) && ($token = trim((string) file_get_contents($path))) !== '') {
            return $token;
        }

        return static::regenerate();
    }

    /** A new token: the old cron URL stops working. */
    public static function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));

        File::ensureDirectoryExists(dirname(static::path()));
        File::put(static::path(), $token.PHP_EOL);

        return $token;
    }

    public static function matches(mixed $value): bool
    {
        return is_string($value) && $value !== '' && hash_equals(static::current(), $value);
    }

    public static function url(): string
    {
        return route('cron', ['token' => static::current()]);
    }

    public static function path(): string
    {
        return (string) config('cron.token_path');
    }
}
