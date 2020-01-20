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
define('CID',false);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Base_AclCommon::is_user())
	die('alert(\'Invalid request\')');

$order = isset($_POST['order']) ? $_POST['order'] : false;

$ret = Utils_CommonDataCommon::get_translated_array($_POST['value'], $order);
if(!$ret) $ret = array();
print(json_encode($ret));
exit();
?>