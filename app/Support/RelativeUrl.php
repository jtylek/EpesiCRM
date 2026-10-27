<?php

namespace App\Support;

/**
 * Links that are stored (bell notifications) must not carry the host they were
 * generated on: a database copied to another machine would still send people
 * to the original site. Path and query survive, so a subdirectory install
 * keeps its prefix.
 */
class RelativeUrl
{
    /**
     * A stored link made to fit this installation: without a host, and under
     * its subdirectory (if any), whichever one the link was made for.
     */
    public static function forThisInstallation(string $url): string
    {
        $path = static::of($url);
        $base = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/');

        return $base !== '' && $path !== $base && ! str_starts_with($path, $base.'/') ? $base.$path : $path;
    }

    public static function of(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return $url;
        }

        return ($parts['path'] ?? '/')
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
