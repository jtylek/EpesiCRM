<?php
if(!isset($_POST['path']) || !isset($_POST['cid']) ||  !isset($_POST['ev_id'])
	 || !isset($_POST['cell_id']))
	die('alert(\'Invalid request\')');

define('JS_OUTPUT',1);
define('CID',$_POST['cid']);
require_once('../../../include.php');
ModuleManager::load_modules();

$mod = Module::static_get_module_variable($_POST['path'],'event_module');
$ev_id = $_POST['ev_id'];
if(!$mod)
	die('alert(\'Invalid request!\')');
if($_POST['cell_id']=='trash') {
	call_user_func(array($mod.'Common','delete'),$ev_id);
} else {
	if(!isset($_POST['month']))
		die('alert(\'Invalid request\')');

	//update event
	$cc = explode('_',$_POST['cell_id']);
	$ev = call_user_func(array($mod.'Common','get'),$ev_id);
	if($_POST['month']) {
		if($ev['timeless']) $cc[1]=(isset($ev['timeless_key'])?$ev['timeless_key']:'timeless');
		else $cc[0] += $ev['start']-strtotime(date('Y-m-d',$ev['start']));
	}

	$ret = call_user_func(array($mod.'Common','update'),$_POST['ev_id'],$cc[0],$ev['duration'],isset($cc[1])?$cc[1]:null);
	if(!$ret) {
		print('reject=true;');
		exit();
	}

	//update content of event on page in client browser
	$ev = call_user_func(array($mod.'Common','get'),$ev_id);
	ob_start();
	Utils_CalendarCommon::print_event($ev);
	$ret = ob_get_clean();
	print('$(\'utils_calendar_event:'.$ev_id.'\').innerHTML=\''.Epesi::escapeJS($ret,false).'\';');
}
?>
