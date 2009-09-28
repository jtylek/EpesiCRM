<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

define("EPESI_VERSION", '1.0.3');

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
if(!defined('READ_ONLY_SESSION')) define('READ_ONLY_SESSION',0);
if(!defined('MOBILE_DEVICE')) define('MOBILE_DEVICE',0);

if(!defined('FIRST_RUN')) define('FIRST_RUN','FirstRun');

//other
@define('SYSTEM_TIMEZONE',date_default_timezone_get());
date_default_timezone_set(SYSTEM_TIMEZONE);

$local_dir = dirname(dirname(str_replace('\\','/',__FILE__)));
define('EPESI_LOCAL_DIR',$local_dir);
$script_filename = str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']);
$detection_failed = strcmp($local_dir,substr($script_filename,0,strlen($local_dir)));
if(!defined('EPESI_DIR')) {
	if(!$detection_failed) {
		$file_url = substr($script_filename,strlen($local_dir));
		$dir_url = substr($_SERVER['SCRIPT_NAME'],0,strlen($_SERVER['SCRIPT_NAME'])-strlen($file_url));
		$dir = trim($dir_url,'/');
		$epesi_dir = '/'.$dir.($dir?'/':'');
		define('EPESI_DIR',$epesi_dir);
	} else {
		trigger_error('Detection of epesi directory failed. Please define EPESI_DIR variable in config.php',E_USER_ERROR);
	}
}

ini_set('arg_separator.output','&');
?>
