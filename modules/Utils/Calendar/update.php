<?php
if(!isset($_POST['path']) || !isset($_POST['cid']) ||  !isset($_POST['ev_id'])
	 || !isset($_POST['cell_id']))
	die('alert(\'Invalid request\')');

$t = microtime(true);

define('JS_OUTPUT',1);
define('CID',$_POST['cid']);
require_once('../../../include.php');
ModuleManager::load_modules();

//error_log('1: '.(microtime(true)-$t)."\n",3,'data/log');

$mod = Module::static_get_module_variable($_POST['path'],'event_module');
$ev_id = $_POST['ev_id'];
if(!$mod)
	die('alert(\'Invalid request!\')');
if($_POST['cell_id']=='trash') {
	$ret = call_user_func(array($mod.'Common','delete'),$ev_id);
	if(!$ret)
		print('reject=true;');
} else {
	if(!isset($_POST['page_type']))
		die('alert(\'Invalid request\')');

	//update event
	$cc = explode('_',$_POST['cell_id']);
	//$cc[0] = Base_RegionalSettingsCommon::reg2time($cc[0]);
	ob_start();
	$ev = call_user_func(array($mod.'Common','get'),$ev_id);
	ob_clean();
//	error_log($ev_id."\n",3,'data/log2');
	if($_POST['page_type']=='month') {
		if($ev['timeless']) $cc[1]=(isset($ev['custom_row_key'])?$ev['custom_row_key']:'timeless');
		else $cc[0] += $ev['start']-strtotime(date('Y-m-d',$ev['start']));
	} else {
		$cc[0] += $ev['start']-strtotime(date('Y-m-d H:00:00',$ev['start']));
	}

//	error_log('2: '.(microtime(true)-$t)."\n",3,'data/log');
	$ret = call_user_func_array(array($mod.'Common','update'),array(& $ev_id,$cc[0],$ev['duration'],isset($cc[1])?$cc[1]:null));
	if(!$ret) {
		print('reject=true;');
		exit();
	}

	//update content of event on page in client browser
//	error_log('2,5: '.(microtime(true)-$t)."\n",3,'data/log');
	ob_start();
	$ev = call_user_func(array($mod.'Common','get'),$ev_id);
	$ret_ev = ob_get_clean();
//	error_log('3: '.(microtime(true)-$t)."\n",3,'data/log');
	if(!$ev) exit();
	if(isset($ev['title']))
		$ev = array($ev);
	foreach($ev as $e) {
		ob_start();
		Utils_CalendarCommon::print_event($e,($_POST['page_type']=='day')?'day':null);
		$ret = ob_get_clean();
		print('$(\'utils_calendar_event:'.$e['id'].'\').innerHTML=\''.Epesi::escapeJS($ret_ev,false).Epesi::escapeJS($ret,false).'\';');
	}
//	error_log('4: '.(microtime(true)-$t)."\n",3,'data/log');
exit();
}
?>
