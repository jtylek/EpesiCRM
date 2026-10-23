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

    /** Whether the web server can run it: its web entry, its config and the login plugin. */
    public static function isInstalled(): bool
    {
        return is_file(static::publicPath().DIRECTORY_SEPARATOR.'index.php')
            && is_file(static::path('config'.DIRECTORY_SEPARATOR.'config.inc.php'))
            && is_file(static::path('plugins'.DIRECTORY_SEPARATOR.'epesi_sso'.DIRECTORY_SEPARATOR.'epesi_sso.php'));
    }

    /** Roundcube's own files are in place, whether or not the web entry works. */
    public static function isDownloaded(): bool
    {
        return is_dir(static::path('program'));
    }

    /**
     * Installed, but the web server can't read the entry or the plugins: what
     * an install made before they were copied looks like where the web server
     * refuses to follow its links (Apache on Windows, for a junction made
     * without administrator rights). lstat() sees the link even then.
     */
    public static function isUnreadable(): bool
    {
        if (static::isInstalled() || ! is_file(static::path('config'.DIRECTORY_SEPARATOR.'config.inc.php'))) {
            return false;
        }

        return @lstat(static::publicPath()) !== false
            || @lstat(static::path('plugins'.DIRECTORY_SEPARATOR.'epesi_sso')) !== false;
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
     * on it, so they also reach Laravel's requests. In a web request it is the
     * folder the app is really served from; only the console, which has no
     * request, falls back to APP_URL.
     */
    public static function cookiePath(): string
    {
        $path = app()->runningInConsole()
            ? (string) parse_url((string) config('app.url'), PHP_URL_PATH)
            : request()->getBasePath();
        $path = trim($path, '/');

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
