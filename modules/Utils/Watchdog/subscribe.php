<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage watchdog
 */
if (!isset($_POST['id']) || !isset($_POST['state']) || !isset($_POST['element']) || !isset($_POST['cat']) || !isset($_POST['cid']))
	die('Invalid request: '.print_r($_POST,true));

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

$id = json_decode($_POST['id']);
$cat = json_decode($_POST['cat']);
$state = json_decode($_POST['state']);
$element = json_decode($_POST['element']);

if (!Acl::is_user()) die('alert("Unauthorized access");');

if ($state)
	Utils_WatchdogCommon::subscribe($cat, $id);
else
	Utils_WatchdogCommon::unsubscribe($cat, $id);

print('$("'.$element.'").innerHTML="'.Epesi::escapeJS(Utils_WatchdogCommon::get_change_subscription_icon_tags($cat, $id)).'";');

?>
