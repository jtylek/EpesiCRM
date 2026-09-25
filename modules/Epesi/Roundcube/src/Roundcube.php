<?php

namespace Epesi\Modules\Roundcube;

/**
 * Where the installed Roundcube lives, and the secrets it shares with Epesi.
 * Every secret is derived from APP_KEY, so the generated Roundcube config can
 * be rewritten at any time and APP_KEY itself never reaches Roundcube.
 */
class Roundcube
{
    public const SESSION_COOKIE = 'epesi_roundcube_sessid';

    public const TICKET_PARAMETER = '_epesi_ticket';

    public static function path(string $path = ''): string
    {
        return rtrim((string) config('epesi-roundcube.path'), '\\/').($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
    }

    public static function publicPath(): string
    {
        return rtrim((string) config('epesi-roundcube.public_path'), '\\/');
    }

    public static function isInstalled(): bool
    {
        return is_file(static::publicPath().DIRECTORY_SEPARATOR.'index.php')
            && is_file(static::path('config'.DIRECTORY_SEPARATOR.'config.inc.php'));
    }

    /**
     * @param  array<string, string>  $query
     */
    public static function url(array $query = []): string
    {
        // The link sits straight in the web root, named like its URL.
        return url(basename(static::publicPath()).'/index.php').($query === [] ? '' : '?'.http_build_query($query));
    }

    /**
     * The app's URL path, e.g. `/epesi-laravel/`: Roundcube's cookies are set
     * on it, so they also reach Laravel's requests.
     */
    public static function cookiePath(): string
    {
        $path = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        return $path === '' ? '/' : '/'.$path.'/';
    }

    public static function table(string $name): string
    {
        return config('epesi-roundcube.table_prefix').$name;
    }

    /** A 32-byte key for one purpose, e.g. `sso` or `des`. */
    public static function key(string $purpose): string
    {
        return hash_hmac('sha256', 'epesi-roundcube:'.$purpose, app('encrypter')->getKey(), true);
    }
}
