<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

define('CID',false);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user() || !isset($_REQUEST['id'])) return;
DB::Execute('UPDATE utils_messenger_users SET done=1 WHERE user_login_id=%d AND message_id=%d',array(Acl::get_user(),$_REQUEST['id']));
?>
