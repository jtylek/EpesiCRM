<?php
if(!isset($_GET['hash']))
	die('');

define('READ_ONLY_SESSION',true);
define('CID',false);
require_once('../../../../include.php');
ModuleManager::load_modules();

DB::Execute('DELETE FROM user_reset_pass WHERE created_on<%T',array(time()-3600*2));

$user_id = DB::GetOne('SELECT user_login_id FROM user_reset_pass WHERE hash_id=%s',array($_GET['hash']));
if($user_id===false){
	
	die(__('Request failed. Authentication link is valid for 2 hours since sending request.'));
	
}
$pass = generate_password();

if(!DB::Execute('UPDATE user_password SET password=%s WHERE user_login_id=%d', array(md5($pass), $user_id))) {
	die(__('Unable to update password for user %s.',array($username)));
}

if(!Base_User_LoginCommon::send_mail_with_password(Base_UserCommon::get_user_login($user_id), $pass, Base_User_LoginCommon::get_mail($user_id), true)) {
	die(__('Unable to send e-mail with password. Mail module configuration invalid. Please contact system administrator.'));
}
DB::Execute('DELETE FROM user_reset_pass WHERE hash_id =%s', array($_GET['hash']));
header('Location: '.get_epesi_url().'?'.http_build_query(array('password_recovered'=>1)));

?>
