<?php
if(!isset($_POST['value']))
	die('alert(\'Invalid request\')');
	
define('JS_OUTPUT',1);
define('SET_SESSION',0);
require_once('../../../include.php');
ModuleManager::load_modules();

$ret = Utils_CommonDataCommon::get_value($_POST['value']);
if(!$ret) $ret = '';
print($ret);

?>