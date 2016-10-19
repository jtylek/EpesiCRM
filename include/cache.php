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
$phpfastcache_config = array(
    "path" => EPESI_LOCAL_DIR . '/' . DATA_DIR.'/cache',
);
if(MEMCACHE_SESSION_SERVER) {
    $srv = explode(':',MEMCACHE_SESSION_SERVER,2);
    $phpfastcache_config['memcache'] = array(array($srv[0],isset($srv[1])?$srv[1]:11211));
}
CacheManager::setDefaultConfig($phpfastcache_config);

class Cache
{
    protected static $cache_object;

    public static function init()
    {
        $drivers = array();
        if( class_exists('Memcached')) $drivers[] = 'Memcached';
        elseif(class_exists('Memcache')) $drivers[] = 'Memcache';
        $drivers = array_merge($drivers,array('Apc','Apcu','Xcache','Zendshm','files'));
        foreach($drivers as $driver) {
            try {
                self::$cache_object = CacheManager::getInstance($driver);
            }catch(Exception $e){}
        }
    }

    public static function get($name, $default = null)
    {
        $ret = self::$cache_object->getItem($name);
        if(is_null($ret->get())) return $default;
        return $ret->get();
    }

    public static function set($name, $value,$expiration_seconds=0)
    {
        $ret = self::$cache_object->getItem($name);
        $ret->set($value);
        if($expiration_seconds>0) $ret->expiresAfter($expiration_seconds);
        self::$cache_object->save($ret);
    }

    public static function clear($name=null)
    {
        if($name) self::$cache_object->deleteItem($name);
        else  self::$cache_object->clear();
    }

}

Cache::init();
