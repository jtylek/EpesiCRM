<?php
/**
 * 
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2014, Xoff Software GmbH
 * @license MIT
 * @version 1.0
 * @package epesi-notify
 * 
 */

ob_start();
define('CID',$_REQUEST['cid']);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Base_AclCommon::is_user())
exit();

$general_setting = Base_NotifyCommon::get_general_setting();

if ($general_setting == -1) {
	echo json_encode(array('disable'=>1));
	exit();
}

$ret = null;
$notify_count = 0;
$group_similar = Base_NotifyCommon::group_similar();
$notifications = Base_NotifyCommon::get_notifications();

foreach ($notifications as $module => $notify) {
    if (!isset($notify['tray'])) continue;
	$timeout = Base_NotifyCommon::get_module_setting($module);
	if ($timeout == -1) continue;

	$msg_count = 0;
	$new_messages = Base_NotifyCommon::get_new_messages($module, $notify['tray']);
	foreach ($new_messages as $id=>$message) {
		$msg_count++;
		if (!$group_similar) {
			$notify_count++;
			if ($notify_count>Base_NotifyCommon::message_refresh_limit) break;
		}

		$_SESSION['Base_Notify']['notified_cache'][$module][$id] = 1;
		
		if ($group_similar && count($new_messages) >1) continue;

		$title = EPESI.' '.Base_NotifyCommon::strip_html($message['title']);
		$body = Base_NotifyCommon::strip_html($message['body']);
		$icon = Base_NotifyCommon::get_icon($module, $message);

		$ret[] = array('title'=>$title, 'opts'=>array('body'=>$body, 'icon'=>$icon), 'timeout'=>$timeout);
	}

	if ($notify_count>Base_NotifyCommon::message_refresh_limit) break;
	if (!$group_similar || $msg_count<=1) continue;
	$notify_count++;
	
	$title = EPESI.' '.Base_NotifyCommon::get_module_caption($module);
	$body = __('%d new notifications', array($msg_count));
	$icon = Base_NotifyCommon::get_icon($module);

	$ret[] = array('title'=>$title, 'opts'=>array('body'=>$body, 'icon'=>$icon), 'timeout'=>$timeout);
}

if (!isset($title) || !isset($icon)) {
	exit();
}

if (isset($ret))
echo json_encode($ret);

exit();
?>
