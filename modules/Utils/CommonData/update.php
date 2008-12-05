<?php
/**
 * @author       Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage CommonData
 */
if(!isset($_POST['value']))
	die('alert(\'Invalid request\')');
	
define('JS_OUTPUT',1);
define('SET_SESSION',0);
require_once('../../../include.php');
ModuleManager::load_modules();

$ret = Utils_CommonDataCommon::get_array($_POST['value']);
if(!$ret) $ret = array();
print(json_encode($ret));
exit();
?>