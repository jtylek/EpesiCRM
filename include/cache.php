<?php
/**
 * @author    Adam Bukowski <abukowski@telaxus.com>
 * @version   1.0
 * @copyright Copyright &copy; 2015, Telaxus LLC
 * @license   MIT
 * @package   epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

use phpFastCache\CacheManager;

class Cache
{
    protected static $cache_object;

    public static function init()
    {
        $drivers = array();
        $phpfastcache_config = array(
            "path" => EPESI_LOCAL_DIR . '/' . DATA_DIR.'/cache',
            "securityKey" => INSTALLATION_ID,
            "defaultTtl" => 86400, // 24h
        );
        if(MEMCACHE_SESSION_SERVER) {
            $srv = explode(':',MEMCACHE_SESSION_SERVER,2);
            $phpfastcache_config['memcache'] = array(array($srv[0],isset($srv[1])?$srv[1]:11211));

            if (class_exists('Memcached')) {
                $drivers[] = 'Memcached';
            } elseif (class_exists('Memcache')) {
                $drivers[] = 'Memcache';
            }
        }
        CacheManager::setDefaultConfig($phpfastcache_config);

        $drivers = array_merge($drivers, array('Apc', 'Apcu', 'Xcache', 'Zendshm', 'files'));
        foreach ($drivers as $driver) {
            try {
                self::$cache_object = CacheManager::getInstance($driver);
                break;
            } catch (Exception $e) {
            }
        }
        if (!self::$cache_object) {
            throw new Exception('No valid cache driver');
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
            $class_uses = class_uses(self::$cache_object);
            if (in_array('phpFastCache\Core\PathSeekerTrait', $class_uses)) {
                self::$cache_object->tmp = array();
            }
        }
    }

}

Cache::init();
