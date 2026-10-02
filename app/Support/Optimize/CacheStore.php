<?php

namespace App\Support\Optimize;

use Illuminate\Cache\MemcachedConnector;
use Memcached;
use Throwable;

/**
 * CACHE_STORE=auto: memcached when this server has it, the file store when
 * it doesn't. A fresh installation's .env gets "auto" (App\Support\Setup\FirstBoot).
 *
 * Whether memcached works is a network question, too slow to ask on every
 * page: on Windows a refused connection can take seconds. Cron asks instead
 * (`epesi:optimize`, every minute while memcached answers, every ten minutes
 * while it doesn't) and writes the answer to a small file, which
 * config/cache.php reads. Without cron the answer is never written, and
 * epesi uses the file store.
 *
 * When memcached stops answering, the next cron run writes "no", which
 * changes FrameworkCaches' fingerprint: a cached config (which names the
 * store it was built with) is dropped, and the next page uses the file store.
 */
class CacheStore
{
    /** A server that didn't answer is asked again after this many seconds. */
    public const RETRY_UNAVAILABLE = 600;

    /** For config/cache.php's 'default'. */
    public static function resolve(?string $store): string
    {
        if ($store !== 'auto') {
            return (string) ($store ?: 'database');
        }

        return static::memcachedAvailable() ? 'memcached' : 'file';
    }

    /** What the last probe found, without asking memcached. */
    public static function memcachedAvailable(): bool
    {
        return extension_loaded('memcached') && (static::readProbe()['available'] ?? false) === true;
    }

    /** Whether .env asks for "auto" (kept in config, since env() is empty once config is cached). */
    public static function isAuto(): bool
    {
        return (bool) config('cache.auto');
    }

    /**
     * Asks memcached again when it is due, and records the answer.
     * Null when CACHE_STORE isn't "auto", so there's nothing to decide.
     */
    public static function refresh(): ?bool
    {
        if (! static::isAuto()) {
            return null;
        }

        $previous = static::readProbe();

        if ($previous !== null && $previous['available'] === false
            && time() - (int) ($previous['checked_at'] ?? 0) < static::RETRY_UNAVAILABLE) {
            return false;
        }

        $available = static::probe();

        static::writeProbe($available);

        return $available;
    }

    /** Connects with the memcached store's own settings, and stores and reads back a value. */
    public static function probe(): bool
    {
        if (! extension_loaded('memcached')) {
            return false;
        }

        $config = (array) config('cache.stores.memcached', []);

        try {
            $memcached = app(MemcachedConnector::class)->connect(
                (array) ($config['servers'] ?? []),
                null,
                [
                    Memcached::OPT_CONNECT_TIMEOUT => 500,
                    Memcached::OPT_SEND_TIMEOUT => 500_000,
                    Memcached::OPT_RECV_TIMEOUT => 500_000,
                ] + (array) ($config['options'] ?? []),
                array_filter((array) ($config['sasl'] ?? [])),
            );

            $key = 'epesi-probe-'.bin2hex(random_bytes(6));
            $works = $memcached->set($key, 'ok', 30) && $memcached->get($key) === 'ok';
            $memcached->delete($key);
            $memcached->quit();

            return $works;
        } catch (Throwable) {
            return false;
        }
    }

    /** Set by a test, so it never writes the application's own. */
    public static ?string $probePath = null;

    public static function probePath(): string
    {
        return static::$probePath ?? storage_path('framework/epesi-memcached.json');
    }

    /**
     * @return array{available: bool, checked_at: int}|null
     */
    public static function readProbe(): ?array
    {
        $path = static::probePath();

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($path), true);

        return is_array($data) && is_bool($data['available'] ?? null) ? $data : null;
    }

    protected static function writeProbe(bool $available): void
    {
        @file_put_contents(static::probePath(), json_encode(['available' => $available, 'checked_at' => time()]), LOCK_EX);
    }
}
