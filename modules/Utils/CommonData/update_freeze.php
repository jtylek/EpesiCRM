<?php
/**
 * @author       Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
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

$ret = Utils_CommonDataCommon::get_value($_POST['value'],true);
if(!$ret) $ret = array();
print(json_encode($ret));
exit();
?>