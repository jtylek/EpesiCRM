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
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

define('CID',false);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user() || !isset($_REQUEST['id'])) return;
DB::Execute('UPDATE utils_messenger_users SET done=1,done_on=%T WHERE user_login_id=%d AND message_id=%d',array(time(),Acl::get_user(),$_REQUEST['id']));
?>
