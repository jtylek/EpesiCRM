<?php
if(!isset($_POST['value']))
	die('alert(\'Invalid request\')');
	
define('JS_OUTPUT',1);
require_once('../../../include.php');
session_write_close(); //don't messup session
ModuleManager::load_modules();

print('var new_opts = '.json_encode(Utils_CommonDataCommon::get_array($_POST['value'])));

?>