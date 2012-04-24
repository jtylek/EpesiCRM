<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage calendar
 */
if(!isset($_POST['path']) || !isset($_POST['cid']) ||  !isset($_POST['ev_id'])
	 || !isset($_POST['cell_id']))
	die('alert(\'Invalid request\')');

$t = microtime(true);

define('JS_OUTPUT',1);
define('CID',$_POST['cid']);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();


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
	if($_POST['page_type']=='month') {
		if(isset($ev['timeless'])) $cc[1]=(isset($ev['custom_row_key'])?$ev['custom_row_key']:'timeless');
		else $cc[0] = strtotime(Base_RegionalSettingsCommon::time2reg($cc[0], true,true,true,false)) + $ev['start'] - strtotime(Base_RegionalSettingsCommon::time2reg($ev['start'], false,true,true,false));
	} else {
		$cc[0] += $ev['start']-strtotime(date('Y-m-d H:00:00',$ev['start']));
	}

	$ret = call_user_func_array(array($mod.'Common','update'),array(& $ev_id,$cc[0],$ev['duration'],isset($cc[1])?$cc[1]:null));
	if(!$ret) {
		print('reject=true;');
		exit();
	}

	//update content of event on page in client browser
	ob_start();
	$ev = call_user_func(array($mod.'Common','get'),$ev_id);
	$ret_ev = ob_get_clean();
	if(!$ev) exit();
	if(isset($ev['title']))
		$ev = array($ev);
	foreach($ev as $e) {
		ob_start();
		Utils_CalendarCommon::print_event($e,($_POST['page_type']=='day')?'day':null,false);
		$ret = ob_get_clean();
		print('document.getElementById(\'utils_calendar_event:'.$ev_id.'\').innerHTML=\''.Epesi::escapeJS($ret_ev.$ret,false).'\';');
	}
exit();
}
?>
