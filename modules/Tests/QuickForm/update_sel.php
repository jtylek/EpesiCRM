<?php
if(!isset($_POST['values']))
	die('alert(\'Invalid request\')');
	
define('JS_OUTPUT',1);
define('SET_SESSION',0);
require_once('../../../include.php');
ModuleManager::load_modules();

$ret = '';
$values = json_decode($_POST['values']);
foreach($values as $v) {
	$ret .= $v;
}

print(json_encode(array('x'=>$ret.'x','y'=>$ret.'y')));

?>