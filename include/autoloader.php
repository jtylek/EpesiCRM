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
//define('DEBUG_AUTOLOADS', 'backtrace');

if (defined('DEBUG_AUTOLOADS') && DEBUG_AUTOLOADS
        && !defined("_VALID_ACCESS")) {
    define('CID', false);
    require_once '../include.php';

    if (isset($_GET['clear'])) {
        unset($_SESSION['debug_autoloads']);
        print "Memory cleared";
        return;
    }
    print("<pre>");
    if (isset($_SESSION['debug_autoloads'])) {
        if (DEBUG_AUTOLOADS == 'count') {
            var_dump($_SESSION['debug_autoloads']);
        } elseif (DEBUG_AUTOLOADS == 'backtrace') {
            foreach ($_SESSION['debug_autoloads'] as $class => $bt) {
                print($class . "\n");
                foreach ($bt as $btx) {
                    var_dump($btx);
                    print("<hr>");
                }
                print("<hr><hr>");
            }
        }
    }
    print("</pre>");
    return;
}

defined("_VALID_ACCESS") || die('Direct access forbidden');

spl_autoload_register('epesi_classes_autoloader');

function epesi_classes_autoloader($class_name) {
    if (defined('DEBUG_AUTOLOADS') && DEBUG_AUTOLOADS) {
        if (!isset($_SESSION['debug_autoloads']))
            $_SESSION['debug_autoloads'] = array();
        if (DEBUG_AUTOLOADS == 'count') {
            if (!isset($_SESSION['debug_autoloads'][$class_name])
                    || !is_numeric($_SESSION['debug_autoloads'][$class_name]))
                $_SESSION['debug_autoloads'][$class_name] = 0;
            $_SESSION['debug_autoloads'][$class_name] += 1;
        } elseif (DEBUG_AUTOLOADS == 'backtrace') {
            if (!isset($_SESSION['debug_autoloads'][$class_name])
                    || !is_array($_SESSION['debug_autoloads'][$class_name]))
                $_SESSION['debug_autoloads'][$class_name] = array();
            $_SESSION['debug_autoloads'][$class_name][] = debug_backtrace();
        }
    }

    $file = 'modules' . DIRECTORY_SEPARATOR;
    $file .= str_replace('_', DIRECTORY_SEPARATOR, $class_name);
    $file .= '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}

?>
