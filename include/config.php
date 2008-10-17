<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

define("EPESI_VERSION", '1.0.0rc4');

require_once(DATA_DIR.'/config.php');
if(!defined('DATABASE_HOST')) trigger_error('Missing defined DATABASE_HOST in '.DATA_DIR.'/config.php.',E_USER_ERROR);
if(!defined('DATABASE_USER')) trigger_error('Missing defined DATABASE_USER in '.DATA_DIR.'/config.php.',E_USER_ERROR);
if(!defined('DATABASE_PASSWORD')) trigger_error('Missing defined DATABASE_PASSWORD in '.DATA_DIR.'/config.php.',E_USER_ERROR);
if(!defined('DATABASE_NAME')) trigger_error('Missing defined DATABASE_NAME in '.DATA_DIR.'/config.php.',E_USER_ERROR);
if(!defined('DATABASE_DRIVER')) trigger_error('Missing defined DATABASE_DRIVER in '.DATA_DIR.'/config.php.',E_USER_ERROR);
if(!defined('DEBUG')) define("DEBUG",0);
if(!defined('MODULE_TIMES')) define("MODULE_TIMES",0);
if(!defined('SQL_TIMES')) define("SQL_TIMES",0);
if(!defined('STRIP_OUTPUT')) define("STRIP_OUTPUT",0);
if(!defined('DISPLAY_ERRORS')) define("DISPLAY_ERRORS",0);
if(!defined('REPORT_ALL_ERRORS')) define("REPORT_ALL_ERRORS",0);
if(!defined('GZIP_HISTORY')) define("GZIP_HISTORY",0);
if(!defined('REDUCING_TRANSFER')) define("REDUCING_TRANSFER",1);
if(!defined('CACHE_COMMON_FILES')) define("CACHE_COMMON_FILES",1);

if(!defined('JS_OUTPUT')) define('JS_OUTPUT',0);
if(!defined('SET_SESSION')) define('SET_SESSION',1);
if(!defined('MOBILE_DEVICE')) define('MOBILE_DEVICE',0);

//other
@define('SYSTEM_TIMEZONE',date_default_timezone_get());

$dir = trim(dirname(dirname(substr(str_replace('\\','/',__FILE__),strlen(str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']))-strlen($_SERVER['SCRIPT_NAME'])))),'/');
define('EPESI_DIR','/'.$dir.'/');
?>
