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

$token = isset($_REQUEST['token'])? $_REQUEST['token']: 0;
$check_user = isset($_REQUEST['check_user'])? $_REQUEST['check_user']: 0;

define('CID', $_REQUEST['cid']);
if (!empty($token) && !$check_user) define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if (!empty($token) && $check_user) {
	if (!Base_NotifyCommon::check_user($token)) {
		Base_NotifyCommon::clear_session_token();
		$token = 0;
	}	
	else 
		exit();
}

$new_instance = Base_NotifyCommon::init_session($token);

if ($new_instance) {
	echo json_encode(array('token'=>$token));
	
	exit();
}

if (Base_NotifyCommon::is_disabled()) {	
	echo json_encode(array('disable'=>1));
	
	exit();
}

if (!Base_NotifyCommon::is_refresh_due($token)) exit();

$ret = array();
$message_count = 0;
$notified_cache = array();
	
$group_similar = Base_NotifyCommon::group_similar();
$refresh_time = time();
$notifications = Base_NotifyCommon::get_notifications($token);
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

Base_NotifyCommon::set_notified_cache($notified_cache, $token, $refresh_time, $all_notified);
	
if (!isset($title) || !isset($icon))
	unset($ret['messages']);
	
echo json_encode($ret);

exit();
?>
