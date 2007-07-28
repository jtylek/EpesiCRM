<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 * @package epesi-base
 */

defined("_VALID_ACCESS") || define("_VALID_ACCESS", true);

umask(0);

require_once('include/include_path.php');
require_once('include/config.php');
require_once('include/error.php');
require_once('include/database.php');
require_once('include/session.php');
require_once('include/variables.php');
require_once('include/history.php');
require_once('include/misc.php');
require_once('include/acl.php');
require_once('include/module_common.php');
require_once('include/module.php');
require_once('include/module_manager.php');
require_once('include/epesi.php');
?>
