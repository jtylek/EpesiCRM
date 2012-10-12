<?php

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2012, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */

//define('DEBUG_AUTOLOADS', 1);

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
        foreach ($_SESSION['debug_autoloads'] as $class => $bt) {
            print($class . "\n");
            foreach ($bt as $btx) {
                var_dump($btx);
                print("<hr>");
            }
            print("<hr><hr>");
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
        if (!isset($_SESSION['debug_autoloads'][$class_name]))
            $_SESSION['debug_autoloads'][$class_name] = array();
        $_SESSION['debug_autoloads'][$class_name][] = debug_backtrace();
    }

    $file = 'modules' . DIRECTORY_SEPARATOR;
    $file .= str_replace('_', DIRECTORY_SEPARATOR, $class_name);
    $file .= '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}

?>
