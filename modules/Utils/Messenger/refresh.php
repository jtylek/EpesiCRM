<?php
/**
 * Popup message to the user
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage Messenger
 */
header("Content-type: text/javascript");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

define('READ_ONLY_SESSION',true);
define('CID',false);
define('JS_OUTPUT',1);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user()) return;
$arr = DB::GetAll('SELECT m.* FROM utils_messenger_message m INNER JOIN utils_messenger_users u ON u.message_id=m.id WHERE u.user_login_id=%d AND u.done=0 AND m.alert_on<%T',array(Acl::get_user(),time()));
//print it out
print('utils_messenger_on=false;');
$t = time();
foreach($arr as $row) {
    if(isset($_SESSION['utils_messenger_holdon'][$row['id']]) && $_SESSION['utils_messenger_holdon'][$row['id']]>$t)
        continue;
        
	ob_start();
	$ret = call_user_func_array(unserialize($row['callback_method']),unserialize($row['callback_args']));
	ob_clean();

	print('if(confirm(\''.Epesi::escapeJS(__('Alert on: %s',array(Base_RegionalSettingsCommon::time2reg($row['alert_on'])))."\n".$ret."\n".($row['message']?__('Alarm comment: %s',array($row['message'])):'')."\n\n".__('Are you sure you want to turn off the alarm?'),false).'\')) new Ajax.Request(\'modules/Utils/Messenger/turnoff.php\',{method:\'get\',parameters:{id:'.$row['id'].'}});');
}

print('utils_messenger_on=true;');
exit();
?>
