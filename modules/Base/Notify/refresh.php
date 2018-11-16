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

define('CID', false);
define('READ_ONLY_SESSION', true);
require_once('../../../include.php');
ModuleManager::load_modules();

ob_start();
$token = Base_NotifyCommon::get_session_token(); // will check is user logged

if ($token === false) {
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
	
		$ret[] = array('title'=>$title, 'opts'=>array('body'=>$body, 'icon'=>$icon), 'timeout'=>$timeout);
	}
	else {	
		foreach ($module_new_notifications as $id=>$message) {
			$message_count++;
			if ($message_count>Base_NotifyCommon::message_refresh_limit) break 2;

			$notified_cache[$module][] = $id;
			
			$title = EPESI.' '.Base_NotifyCommon::strip_html($message['title']);
			$body = Base_NotifyCommon::strip_html($message['body']);
			$icon = Base_NotifyCommon::get_icon($module, $message);
	
			$ret[] = array('title'=>$title, 'opts'=>array('body'=>$body, 'icon'=>$icon, 'tag'=>$id), 'timeout'=>$timeout);
		}
	}

	$all_notified &= count($module_new_notifications) == count($notified_cache[$module]);
}

Base_NotifyCommon::set_notified_cache($notified_cache, $token, $all_notified ? $refresh_time : Base_NotifyCommon::get_last_refresh($token));

ob_end_clean();

if (count($ret)) {
    echo json_encode(array('messages' => $ret));
}

exit();
