<?php

namespace App\Support\Setup;

use Illuminate\Support\Facades\File;

/**
 * The one-time code the setup wizard asks for first. Until setup is done,
 * whoever opens /setup could otherwise make themselves the administrator —
 * and, now that the database is chosen in the browser too, point epesi at a
 * database of their own. The code lives in a file on the server
 * (storage/app/setup-code.txt), so only someone who can read the server's
 * files can run setup. `php artisan epesi:install` prints it as well.
 *
 * SETUP_TOKEN in .env, when set, is used instead: that is a code the
 * administrator chose themselves before deploying.
 *
 * The file is deleted once setup has installed the system.
 */
class SetupCode
{
    /** Session key remembering that this browser gave the right code. */
    public const SESSION_KEY = 'setup.code_verified';

    /** No 0/O, 1/I/L: the code is read off a file and typed back in. */
    protected const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function current(): string
    {
        $token = (string) config('setup.token');

        if ($token !== '') {
            return $token;
        }

        $path = static::path();

        if (is_file($path) && ($code = trim((string) file_get_contents($path))) !== '') {
            return $code;
        }

        $code = static::generate();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $code.PHP_EOL);

        return $code;
    }

    public static function usesToken(): bool
    {
        return (string) config('setup.token') !== '';
    }

    public static function matches(mixed $value): bool
    {
        return is_string($value) && hash_equals(static::normalize(static::current()), static::normalize($value));
    }

    /** Whether this browser session has already given the right code. */
    public static function verified(): bool
    {
        $hash = session(static::SESSION_KEY);

        return is_string($hash) && hash_equals($hash, static::hash());
    }

    public static function markVerified(): void
    {
        session()->put(static::SESSION_KEY, static::hash());
    }

    public static function forget(): void
    {
        File::delete(static::path());
    }

    public static function path(): string
    {
        return (string) config('setup.code_path');
    }

    /**
     * Where to tell someone to look for the code, relative to the
     * application when it is inside it.
     */
    public static function displayPath(): string
    {
        $path = static::path();
        $base = rtrim(base_path(), '/\\').DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? str_replace('\\', '/', substr($path, strlen($base))) : $path;
    }

    protected static function generate(): string
    {
        $alphabet = static::ALPHABET;
        $code = '';

        for ($i = 0; $i < 12; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return implode('-', str_split($code, 4));
    }

    /**
     * A generated code is compared ignoring case, spaces and dashes; a
     * SETUP_TOKEN exactly as written.
     */
    protected static function normalize(string $value): string
    {
        return static::usesToken() ? $value : strtoupper((string) preg_replace('/[\s-]+/', '', $value));
    }

    protected static function hash(): string
    {
        return hash_hmac('sha256', static::normalize(static::current()), (string) config('app.key'));
    }
}
