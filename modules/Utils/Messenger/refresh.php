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

/*
 * Early-out before ModuleManager::load_modules(), the same shape Base/Notify/refresh.php
 * uses (see the long comment there for the reasoning and the fail-open rule).
 *
 * The browser polls this every 180s for as long as anyone is logged in, and on almost
 * every poll there is no alarm due - but finding that out used to cost the full
 * application bootstrap, all ~95 installed modules. The one query below answers it using
 * nothing but the session and DB, both of which include.php has already set up.
 *
 * It is the *same* query the full path runs, reduced to an existence check, so it cannot
 * disagree with it: rows that exist but are held off by $_SESSION['utils_messenger_holdon']
 * still fall through to the original code, which decides for real. Deliberately fail-open
 * - no session user means no early-out.
 *
 * The output is what a no-alarms full run emits. That path prints utils_messenger_on=false
 * on the way in (so a slow round of confirm dialogs cannot overlap the next poll) and
 * utils_messenger_on=true on the way out; with no dialogs in between, the end state a
 * client sees is just the 'true'. Headers are already sent above, before include.php, so
 * an early exit still carries the right Content-type.
 */
if (!empty($_SESSION['user'])) {
    $pending = DB::GetOne('SELECT m.id FROM utils_messenger_message m INNER JOIN utils_messenger_users u ON u.message_id=m.id WHERE u.user_login_id=%d AND u.done=0 AND m.alert_on<%T LIMIT 1',
                          array($_SESSION['user'], time()));
    if (!$pending) {
        print('utils_messenger_on=true;');
        exit();
    }
}

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

	$msg = __('Alert on: %s',array(Base_RegionalSettingsCommon::time2reg($row['alert_on'])))."\n".$ret."\n".($row['message']?__('Alarm comment: %s',array($row['message'])):'')."\n\n".__('Are you sure you want to turn off the alarm?');
	$action = 'jQuery.ajax(\'modules/Utils/Messenger/turnoff.php\',{method:\'get\',data:{id:'.$row['id'].'}});';
	print(Module::wrap_confirm_js($msg, $action));
}

print('utils_messenger_on=true;');
exit();
?>
