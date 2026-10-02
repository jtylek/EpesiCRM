<?php

namespace App\Support\Optimize;

use Illuminate\Cache\MemcachedStore;

/**
 * Laravel's memcached store, sending a lifetime of up to 30 days as seconds
 * from now rather than as a Unix time.
 *
 * Laravel always sends a Unix time, which only works while the memcached
 * server's clock agrees with PHP's. The memcached 1.4.4 Windows build many
 * XAMPP machines run reports a time in 1987, so it took every such entry as
 * already expired: every cache read with a lifetime missed, while forever()
 * worked. Memcached itself reads a value up to 30 days as relative, on any
 * server, whatever its clock; beyond that it needs the Unix time.
 *
 * Registered for the "memcached" driver in bootstrap/app.php.
 */
class RelativeExpiryMemcachedStore extends MemcachedStore
{
    protected const RELATIVE_LIMIT = 60 * 60 * 24 * 30;

    protected function calculateExpiration($seconds)
    {
        return $seconds > 0 && $seconds <= self::RELATIVE_LIMIT ? (int) $seconds : $this->toTimestamp($seconds);
    }
}
