<?php
/**
 * @author    Adam Bukowski <abukowski@telaxus.com>
 * @version   1.0
 * @copyright Copyright &copy; 2015, Telaxus LLC
 * @license   MIT
 * @package   epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;

class Cache
{
    protected static $cache_object;

    /** Driver actually in use, for diagnostics - see active_driver(). */
    protected static $active_driver;

    public static function init()
    {
        foreach (self::driver_chain() as $driver) {
            try {
                self::$cache_object = CacheManager::getInstance($driver, self::config_for($driver));
                self::$active_driver = $driver;
                break;
            } catch (Exception) {
                // Covers phpfastcache's own PhpfastcacheDriverCheckException (it extends
                // Exception), which is what a pinned driver with a missing extension
                // throws - so that case falls through to the next driver in the chain
                // rather than taking the install down.
            }
        }
        if (!self::$cache_object) {
            throw new Exception('No valid cache driver');
        }
    }

    /**
     * Drivers to try, in order.
     *
     * CACHE_TYPE names one explicitly; anything else still gets the fallback chain
     * appended, because a pinned driver whose extension turns out to be missing should
     * degrade to a working cache rather than take the whole install down. That failure
     * mode is not hypothetical: this app spent an unknown stretch silently running its
     * Roundcube cache on 'db' because a Memcache/Memcached class check picked the wrong
     * one (item 1.5, fixed 2026-08-31).
     *
     * 'auto' is the historical chain exactly: memcached only when a server is configured,
     * then the local drivers. Sqlite is new to the chain and sits ahead of Files - it is
     * a real win for installs with no memcached and no APCu, which before this had
     * nothing but one file per cache entry.
     */
    protected static function driver_chain()
    {
        $networked = array('memcached' => 'Memcached', 'memcache' => 'Memcache', 'redis' => 'Redis', 'predis' => 'Predis');
        $local     = array('apcu' => 'Apcu', 'zendshm' => 'Zendshm', 'sqlite' => 'Sqlite', 'files' => 'Files');
        $type = strtolower((string) CACHE_TYPE);

        $chain = array();
        if (isset($networked[$type])) {
            if (!CACHE_SERVER) trigger_error('CACHE_TYPE is "'.$type.'" but CACHE_SERVER is empty', E_USER_WARNING);
            $chain[] = $networked[$type];
        } elseif (isset($local[$type])) {
            $chain[] = $local[$type];
        } elseif ($type !== 'auto' && $type !== '') {
            trigger_error('Unknown CACHE_TYPE "'.$type.'", falling back to auto', E_USER_WARNING);
        }

        if (!$chain && CACHE_SERVER) {
            // 'auto'. class_exists() rather than the driver name, because both extensions
            // provide a class of their own name and only one is usually present.
            if (class_exists('Memcached')) $chain[] = 'Memcached';
            elseif (class_exists('Memcache')) $chain[] = 'Memcache';
        }

        // Apc/Xcache no longer exist as of phpfastcache 9.x (both extensions are long dead).
        foreach (array('Apcu', 'Zendshm', 'Sqlite', 'Files') as $fallback) {
            if (!in_array($fallback, $chain)) $chain[] = $fallback;
        }
        return $chain;
    }

    /** Which driver init() settled on. Worth reading rather than inferring from the
     *  extension list - see AI-shared/optimization-plan-opus.md section 7 note 6. */
    public static function active_driver()
    {
        return self::$active_driver;
    }

    // Each driver's Config class only accepts its own specific set of properties
    // (e.g. Memcache(d)'s "servers", Files' "path"/"securityKey"), so build the
    // exact class per driver rather than a single shared config array.
    protected static function config_for($driver)
    {
        $defaultTtl = 86400; // 24h
        switch ($driver) {
            case 'Memcached':
            case 'Memcache':
                $srv = explode(':', CACHE_SERVER, 2);
                $configClass = $driver === 'Memcached'
                    ? \Phpfastcache\Drivers\Memcached\Config::class
                    : \Phpfastcache\Drivers\Memcache\Config::class;
                return new $configClass([
                    'defaultTtl' => $defaultTtl,
                    'servers' => [['host' => $srv[0], 'port' => (int)($srv[1] ?? 11211)]],
                ]);
            case 'Redis':
            case 'Predis':
                $srv = explode(':', CACHE_SERVER, 2);
                $configClass = $driver === 'Redis'
                    ? \Phpfastcache\Drivers\Redis\Config::class
                    : \Phpfastcache\Drivers\Predis\Config::class;
                return new $configClass([
                    'defaultTtl' => $defaultTtl,
                    'host' => $srv[0] ?: '127.0.0.1',
                    'port' => (int)($srv[1] ?? 6379),
                ]);
            case 'Sqlite':
                // Same directory as the Files driver: regenerable cache output belongs in
                // TEMP_DIR, not DATA_DIR (see include/config.php and the 20260829 patch).
                return new \Phpfastcache\Drivers\Sqlite\Config([
                    'path' => EPESI_LOCAL_DIR . '/' . TEMP_DIR . '/cache',
                    'securityKey' => INSTALLATION_ID,
                    'defaultTtl' => $defaultTtl,
                ]);
            case 'Files':
                return new \Phpfastcache\Drivers\Files\Config([
                    'path' => EPESI_LOCAL_DIR . '/' . TEMP_DIR . '/cache',
                    'securityKey' => INSTALLATION_ID,
                    'defaultTtl' => $defaultTtl,
                ]);
            default:
                return new ConfigurationOption(['defaultTtl' => $defaultTtl]);
        }
    }

    public static function get($name, $default = null)
    {
        $name = 'epesi_' . INSTALLATION_ID . '_' . $name;
        $ret = self::$cache_object->getItem($name);
        if(is_null($ret->get())) return $default;
        return $ret->get();
    }

    public static function set($name, $value,$expiration_seconds=0)
    {
        $name = 'epesi_' . INSTALLATION_ID . '_' . $name;
        $ret = self::$cache_object->getItem($name);
        $ret->set($value);
        if($expiration_seconds>0) $ret->expiresAfter($expiration_seconds);
        self::$cache_object->save($ret);
    }

    public static function clear($name=null)
    {
        if ($name) {
            $name = 'epesi_' . INSTALLATION_ID . '_' . $name;
            self::$cache_object->deleteItem($name);
        } else {
            self::$cache_object->clear();
        }
    }

}

Cache::init();
