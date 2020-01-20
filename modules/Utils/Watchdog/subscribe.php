<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-utils
 * @subpackage watchdog
 */
if (!isset($_POST['id']) || !isset($_POST['state']) || !isset($_POST['element']) || !isset($_POST['cat']) || !isset($_POST['cid']))
	die('Invalid request');

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

print('jq("#'.$element.'").html("'.Epesi::escapeJS(Utils_WatchdogCommon::get_change_subscription_icon_tags($cat, $id)).'");');

?>
