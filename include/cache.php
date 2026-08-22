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

    public static function init()
    {
        $drivers = array();
        if(MEMCACHE_SESSION_SERVER) {
            if (class_exists('Memcached')) {
                $drivers[] = 'Memcached';
            } elseif (class_exists('Memcache')) {
                $drivers[] = 'Memcache';
            }
        }
        // Apc/Xcache no longer exist as of phpfastcache 9.x (both extensions are long dead).
        $drivers = array_merge($drivers, array('Apcu', 'Zendshm', 'Files'));

        foreach ($drivers as $driver) {
            try {
                self::$cache_object = CacheManager::getInstance($driver, self::config_for($driver));
                break;
            } catch (Exception) {
            }
        }
        if (!self::$cache_object) {
            throw new Exception('No valid cache driver');
        }
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
                $srv = explode(':', MEMCACHE_SESSION_SERVER, 2);
                $configClass = $driver === 'Memcached'
                    ? \Phpfastcache\Drivers\Memcached\Config::class
                    : \Phpfastcache\Drivers\Memcache\Config::class;
                return new $configClass([
                    'defaultTtl' => $defaultTtl,
                    'servers' => [['host' => $srv[0], 'port' => (int)($srv[1] ?? 11211)]],
                ]);
            case 'Files':
                return new \Phpfastcache\Drivers\Files\Config([
                    'path' => EPESI_LOCAL_DIR . '/' . DATA_DIR . '/cache',
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
