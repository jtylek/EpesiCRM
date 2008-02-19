<?php
if(!isset($_POST['values']) || !isset($_POST['req_url']))
	die('alert(\'Invalid request\')');
	
define('JS_OUTPUT',1);
define('CID',false); 
//define('SET_SESSION',0);
require_once('../../../include.php');
ModuleManager::load_modules();

$ret = '';
$_POST['values'] = json_decode($_POST['values']);
$_REQUEST['values'] = $_GET['values'] = $_POST['values'];

require_once($_POST['req_url']);

?>