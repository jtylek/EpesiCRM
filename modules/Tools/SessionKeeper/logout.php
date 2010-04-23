<?php
header("Content-type: text/javascript");

define('JS_OUTPUT',1);
require_once('../../../include.php');
if(Acl::is_user()) {
    DB::Execute('UPDATE user_password SET autologin_id=\'\' WHERE user_login_id=%d',array(Acl::get_user()));
    Acl::set_user();
    die('document.location=\'index.php\';');
}
?>
