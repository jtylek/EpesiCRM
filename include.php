<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */

defined("_VALID_ACCESS") || define("_VALID_ACCESS", true);

umask(0755);

chdir(dirname(__FILE__));
//ini_set('include_path','');
require_once('include/data_dir.php');
require_once('include/config.php');
require_once('include/epesi.php');
require_once('include/error.php');
if(JS_OUTPUT)
	ob_start(array('ErrorHandler','handle_fatal'));
require_once('include/magicquotes.php');
require_once('include/database.php');
require_once('include/session.php');
require_once('include/variables.php');
require_once('include/history.php');
require_once('include/misc.php');
require_once('include/acl.php');
require_once('include/module_acl.php');
require_once('include/module_primitive.php');
require_once('include/module_install.php');
require_once('include/module_common.php');
require_once('include/module.php');
require_once('include/module_manager.php');
if(JS_OUTPUT)
	ob_end_clean();
?>
