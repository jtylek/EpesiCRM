<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */

defined("_VALID_ACCESS") || define("_VALID_ACCESS", true);

umask(0022);

chdir(dirname(__FILE__));
try {
    require_once('include/include_path.php');
    require_once('include/data_dir.php');
    require_once('include/config.php');
    require_once('include/maintenance_mode.php');
    require_once('include/epesi.php');
    require_once('include/error.php');
    require_once('include/magicquotes.php');
    require_once('include/database.php');
    require_once('include/cache.php');
    require_once('include/misc.php');
    require_once('include/module_primitive.php');
    require_once('include/module_install.php');
    require_once('include/module_common.php');
    require_once('include/module.php');
    require_once('include/module_manager.php');
    require_once('include/autoloader.php');
    require_once('include/session.php');
    require_once('include/variables.php');
    require_once('include/history.php');
    require_once('include/patches.php');
    require_once('include/simple_login.php');
} catch (Exception $e) {
    die($e->getMessage());
}
