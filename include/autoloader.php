<?php

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2012, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */

/*
 * Use below defines to debug autoloading feature.
 * 'count' - count called includes for specific class name
 * 'backtrace' - stores backtraces to track place which called autoloader
 * 
 * You can see debug info only when it's enabled.
 * Open your browser to this file.
 * EPESI_ADDRESS/include/autoloader.php
 */
//define('DEBUG_AUTOLOADS', 'count');
//define('DEBUG_AUTOLOADS', 'place');

if (defined('DEBUG_AUTOLOADS') && DEBUG_AUTOLOADS
        && !defined("_VALID_ACCESS")) {
    define('CID', false);
    require_once '../include.php';

    print("<pre>");
    if (isset($_GET['clear'])) {
        unset($_SESSION['debug_autoloads']);
        print "Memory cleared\n";
        print "<a href=\"autoloader.php\">Back</a>";
        return;
    }
    print("click <a href=\"?clear=1\">here</a> to clear stored data\n\n");
    ob_start();
    if (isset($_SESSION['debug_autoloads'])) {
        if (DEBUG_AUTOLOADS == 'count') {
            var_dump($_SESSION['debug_autoloads']);
        } elseif (DEBUG_AUTOLOADS == 'place') {
            foreach ($_SESSION['debug_autoloads'] as $class => $places) {
                print($class . "\n");
                foreach ($places as $place) {
                    print "\t$place\n";
                }
                print("<hr>");
            }
        }
    }
    $data = ob_get_clean();
    if (!$data)
        $data = "No data stored. Refresh EPESI.";
    print($data);
    print("</pre>");
    return;
}

defined("_VALID_ACCESS") || die("Direct access forbidden<br>Define DEBUG_AUTOLOADS and come back here.");

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

    private function get_calling_place() {
        $db = debug_backtrace();
        $autoload_called = false;
        foreach ($db as $entry) {
            if (isset($entry['function']) &&
                    ($entry['function'] == 'spl_autoload_call' || $entry['function'] == 'autoload'))
                $autoload_called = true;
            if ($autoload_called && isset($entry['file']) && $entry['file'] && isset($entry['line'])) {
                return $entry['file'] . ':' . $entry['line'];
            }
        }
        return 'not found';
    }

    private function autoload($class_name) {
        if (defined('DEBUG_AUTOLOADS') && DEBUG_AUTOLOADS) {
            if (!isset($_SESSION['debug_autoloads']))
                $_SESSION['debug_autoloads'] = array();
            if (DEBUG_AUTOLOADS == 'count') {
                if (!isset($_SESSION['debug_autoloads'][$class_name])
                        || !is_numeric($_SESSION['debug_autoloads'][$class_name]))
                    $_SESSION['debug_autoloads'][$class_name] = 0;
                $_SESSION['debug_autoloads'][$class_name] += 1;
            } elseif (DEBUG_AUTOLOADS == 'place') {
                if (!isset($_SESSION['debug_autoloads'][$class_name])
                        || !is_array($_SESSION['debug_autoloads'][$class_name]))
                    $_SESSION['debug_autoloads'][$class_name] = array();
                $_SESSION['debug_autoloads'][$class_name][] = $this->get_calling_place();
            }
        }

        if ($this->module_common($class_name))
            return true;

        if ($this->module_main($class_name))
            return true;

        if ($this->class_from_modules($class_name))
            return true;

        return false;
    }

    private function class_from_modules($class_name) {
        $file = 'modules' . DIRECTORY_SEPARATOR;
        $file .= str_replace('_', DIRECTORY_SEPARATOR, $class_name);
        $file .= '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
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
        return false;
    }

    private function module_main($class_name) {
        if (isset(ModuleManager::$modules[$class_name])) {
            return ModuleManager::include_main($class_name, ModuleManager::$modules[$class_name]);
        }
        return false;
    }

}
