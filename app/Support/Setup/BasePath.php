<?php

namespace App\Support\Setup;

/**
 * Lines up the request's script path with its address before Laravel reads
 * them, for the two ways epesi is served from outside its public/ folder:
 *
 * - Unpacked into a web root (example.com/crm/): the top-level .htaccess
 *   rewrites every request into public/ internally, so the address stays
 *   /crm/login while SCRIPT_NAME says /crm/public/index.php. Laravel works
 *   its base path out from their common prefix, finds none, and every route
 *   404s. Detected here by the address not being under .../public.
 * - LARAVEL_BASE_PATH, set in the web server's configuration for a vhost that
 *   rewrites a path straight to public/ (this project's XAMPP development
 *   setup). Applied only to requests under that path: set server-wide, it
 *   would otherwise reach every other copy under htdocs.
 *
 * Either way SCRIPT_NAME becomes <base>/index.php. Everywhere else — a
 * document root that is public/ itself, `php artisan serve`, nginx — nothing
 * changes. Runs from public/index.php before the application boots.
 */
class BasePath
{
    /**
     * @param  array<string, mixed>  $server  $_SERVER
     * @return array<string, mixed>
     */
    public static function fix(array $server): array
    {
        $uri = (string) ($server['REQUEST_URI'] ?? '');
        $base = rtrim((string) ($server['LARAVEL_BASE_PATH'] ?? ''), '/');

        if ($base === '' || ! static::under($uri, $base)) {
            $base = static::rootFolder($server, $uri);
        }

        if ($base === null || ! static::under($uri, $base)) {
            return $server;
        }

        $server['SCRIPT_NAME'] = $base.'/index.php';

        // Symfony's base-path matching needs a "/" right after $base to line
        // up; a bare request for exactly $base (no trailing slash) would
        // otherwise be routed as a 404.
        if ($uri === $base || str_starts_with($uri, $base.'?')) {
            $server['REQUEST_URI'] = $base.'/'.substr($uri, strlen($base));
        }

        return $server;
    }

    /**
     * The folder above public/ ("/crm", or "" at the site root) when this
     * request reached public/index.php through the top-level .htaccess, i.e.
     * its address isn't under .../public; null otherwise.
     *
     * @param  array<string, mixed>  $server
     */
    protected static function rootFolder(array $server, string $uri): ?string
    {
        $public = rtrim(str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? ''))), '/');

        if (! str_ends_with($public, '/public') || static::under($uri, $public)) {
            return null;
        }

        return substr($public, 0, -strlen('/public'));
    }

    protected static function under(string $uri, string $path): bool
    {
        $path = rtrim($path, '/');

        if ($path === '') {
            return str_starts_with($uri, '/');
        }

        return $uri === $path || str_starts_with($uri, $path.'/') || str_starts_with($uri, $path.'?');
    }
}
