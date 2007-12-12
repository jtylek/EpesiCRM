<?php
if(!isset($_POST['path']) || !isset($_POST['cid']) ||  !isset($_POST['ev_id']) || !isset($_POST['cell_id']))
	die('alert(\'Invalid request\')');
	
define('JS_OUTPUT',1);
define('CID',$_POST['cid']);
require_once('../../../include.php');
ModuleManager::load_modules();

$mod = Module::static_get_module_variable($_POST['path'],'event_module');
if(!$mod)
	die('alert(\'Invalid request!\')');
if($_POST['cell_id']=='trash') {
	call_user_func(array($mod.'Common','delete'),$_POST['ev_id']);
} else {
	$cc = explode('_',$_POST['cell_id']);
	call_user_func(array($mod.'Common','update'),$_POST['ev_id'],$cc[0],isset($cc[1]));
}
?>