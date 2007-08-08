<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

define("EPESI_VERSION", '0.8.8');

require_once('data/config.php');
if(!defined('DATABASE_HOST')) trigger_error('Missing defined DATABASE_HOST in data/config.php.',E_USER_ERROR);
if(!defined('DATABASE_USER')) trigger_error('Missing defined DATABASE_USER in data/config.php.',E_USER_ERROR);
if(!defined('DATABASE_PASSWORD')) trigger_error('Missing defined DATABASE_PASSWORD in data/config.php.',E_USER_ERROR);
if(!defined('DATABASE_NAME')) trigger_error('Missing defined DATABASE_NAME in data/config.php.',E_USER_ERROR);
if(!defined('DATABASE_DRIVER')) trigger_error('Missing defined DATABASE_DRIVER in data/config.php.',E_USER_ERROR);
if(!defined('DB_SESSION')) define('DB_SESSION',true);
if(!defined('DEBUG')) define("DEBUG",0);
if(!defined('MODULE_TIMES')) define("MODULE_TIMES",0);
if(!defined('SQL_TIMES')) define("SQL_TIMES",0);
if(!defined('SQL_TYPE_CONTROL')) define("SQL_TYPE_CONTROL",0);
if(!defined('SECURE_HTTP')) define("SECURE_HTTP",0);
if(!defined('STRIP_OUTPUT')) define("STRIP_OUTPUT",0);
if(!defined('DISPLAY_ERRORS')) define("DISPLAY_ERRORS",0);
?>
