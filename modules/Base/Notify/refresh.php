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
//unset($_SESSION['Base_Notify']);
$ret = null;
$pileup = -1;
$group_similar = Base_NotifyCommon::group_similar();
$notifications = Base_NotifyCommon::get_notifications();

foreach ($notifications as $module=>$notify) {
	$timeout = Base_NotifyCommon::get_module_setting($module);
	if ($timeout == -1) continue;

	foreach ($notify['tray'] as $id=>$message) {
		if (isset($_SESSION['Base_Notify']['notified_cache'][$module][$id])) continue;
		$pileup++;
		if ($pileup>=1 && !$group_similar) break;

		$_SESSION['Base_Notify']['notified_cache'][$module][$id] = 1;

		$title = EPESI.' '.Base_NotifyCommon::strip_html($message['title']);
		$body = Base_NotifyCommon::strip_html($message['body']);
		$icon = Base_NotifyCommon::get_icon($module, $message);
	}

	if (($pileup>=0 && !$group_similar) || $pileup == 0) break;
	if ($pileup == -1) continue;
	$title = EPESI.' '.__('Module %s', array(Base_NotifyCommon::get_module_caption($module)));
	$body = __('%d new notifications', array($pileup+1));
	break;
}

if (!isset($title) || !isset($icon)) {
	exit();
}

$ret = array('title'=>$title, 'opts'=>array('body'=>$body, 'icon'=>$icon), 'timeout'=>$timeout, 'pileup'=>$pileup);

if (isset($ret))
echo json_encode($ret);

exit();
?>
