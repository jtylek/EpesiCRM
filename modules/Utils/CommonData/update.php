<?php
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