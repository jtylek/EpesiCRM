<?php
/**
 * 
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2014, Xoff Software GmbH
 * @license MIT
 * @version 2.0
 * @package epesi-notify
 * 
 */

$last_refresh = isset($_REQUEST['last_refresh'])? $_REQUEST['last_refresh']: 0;
$token = isset($_REQUEST['token'])? $_REQUEST['token']: 0;
$check_user = isset($_REQUEST['check_user'])? $_REQUEST['check_user']: 0;

define('CID', $_REQUEST['cid']);
if (!empty($token) && !$check_user) define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if ($check_user) {
	if (!Base_NotifyCommon::check_user($token)) {
		Base_NotifyCommon::delete_session($token);
		$token = 0;
	}
	else {
		echo json_encode(array('token'=>$token));
		
		exit();
	}
}

$new_session = Base_NotifyCommon::init_session($token);

if ($new_session) {
	echo json_encode(array('token'=>$token, 'last_refresh'=>Base_NotifyCommon::get_last_refresh()));
	
	exit();
}

if (Base_NotifyCommon::is_disabled()) {	
	echo json_encode(array('disable'=>1));
	
	exit();
}


$ret = array();
$message_count = 0;
$notified_cache = array();
	
$group_similar = Base_NotifyCommon::group_similar();
$refresh_time = time();
$notifications = Base_NotifyCommon::get_notifications($token, $last_refresh);
$all_notified = true;
	
foreach ($notifications as $module => $module_new_notifications) {
	$timeout = Base_NotifyCommon::get_module_setting($module);

	if ($group_similar && count($module_new_notifications) > 1) {
		$message_count++;
		if ($message_count>Base_NotifyCommon::message_refresh_limit) break;
			
		$notified_cache[$module] = array_keys($module_new_notifications);
			
		$title = EPESI.' '.Base_NotifyCommon::get_module_caption($module);
		$body = __('%d new notifications', array(count($module_new_notifications)));
		$icon = Base_NotifyCommon::get_icon($module);
	
		$ret['messages'][] = array('title'=>$title, 'opts'=>array('body'=>$body, 'icon'=>$icon), 'timeout'=>$timeout);
	}
	else {	
		foreach ($module_new_notifications as $id=>$message) {
			$message_count++;
			if ($message_count>Base_NotifyCommon::message_refresh_limit) break 2;
			
			$notified_cache[$module][] = $id;
			
			$title = EPESI.' '.Base_NotifyCommon::strip_html($message['title']);
			$body = Base_NotifyCommon::strip_html($message['body']);
			$icon = Base_NotifyCommon::get_icon($module, $message);
	
			$ret['messages'][] = array('title'=>$title, 'opts'=>array('body'=>$body, 'icon'=>$icon), 'timeout'=>$timeout);
		}
	}
	
	$all_notified &= count($module_new_notifications) == count($notified_cache[$module]);
}

Base_NotifyCommon::set_notified_cache($notified_cache, $token, $refresh_time);
	
if (!isset($title) || !isset($icon))
	unset($ret['messages']);
	
if (!$all_notified)
	$refresh_time = $last_refresh;
	
$ret['last_refresh'] = $refresh_time;

echo json_encode($ret);

exit();
?>
