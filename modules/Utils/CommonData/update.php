<?php
if(!isset($_POST['value']))
	die('alert(\'Invalid request\')');
	
define('JS_OUTPUT',1);
define('SET_SESSION',0);
require_once('../../../include.php');
ModuleManager::load_modules();

print(json_encode(Utils_CommonDataCommon::get_array($_POST['value'])));

?>