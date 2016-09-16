<?php

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2012, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */

defined("_VALID_ACCESS") || die("Direct access forbidden");

class ModulesAutoloader {

    private static $instance = null;

    public static function enable() {
        if (self::$instance === null) {
            self::$instance = new ModulesAutoloader();
        }
    }

    public static function disable() {
        if (self::$instance)
            unset(self::$instance);
    }

    private function __construct() {
        spl_autoload_register(array($this, 'autoload'));
    }

    public function __destruct() {
        spl_autoload_unregister(array($this, 'autoload'));
    }

    private function autoload($class_name) {
        if ($this->module_common($class_name))
            return true;

        if ($this->module_install($class_name))
            return true;

        if ($this->module_main($class_name))
            return true;

        return false;
    }

    private function module_common($class_name) {
        if (substr($class_name, -6) != 'Common')
            return false;

        $module_name = substr($class_name, 0, -6);
        if (isset(ModuleManager::$modules[$module_name])) {
            ModuleManager::include_common($module_name, ModuleManager::$modules[$module_name]);
            // here return true to prevent further file searching.
            // it should exists such file in module but it doesn't
            return true;
        }
    }

    private function module_install($class_name)
    {
        if (substr($class_name, -7) != 'Install') {
            return false;
        }

        $module_name = substr($class_name, 0, -7);
        return ModuleManager::include_install($module_name);
    }

    private function module_main($class_name) {
        if (isset(ModuleManager::$modules[$class_name])) {
            return ModuleManager::include_main($class_name, ModuleManager::$modules[$class_name]);
        }
        return false;
    }

}

ModulesAutoloader::enable();
require_once('vendor/autoload.php');
