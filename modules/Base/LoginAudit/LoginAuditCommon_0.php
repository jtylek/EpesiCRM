<?php
/**
 * Provides login audit log
 * @author pbukowski@telaxus.com & jtylek@telaxus.com
 * @copyright pbukowski@telaxus.com & jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_LoginAuditCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Login Audit";
	}

	public static function applet_info() {
		return "Tracks users logins";
	}
	
	public static function body_access() {
		return Acl::is_user();
	}
}
if(isset($_SESSION['base_login_audit']) && $_SESSION['base_login_audit_user']==Acl::get_user()) {
	DB::Execute('UPDATE base_login_audit SET end_time=%T WHERE id=%d',array(time(),$_SESSION['base_login_audit']));
} elseif(Acl::is_user()) {
	$now = time();
	DB::Execute('INSERT INTO base_login_audit(user_login_id,start_time,end_time) VALUES(%d,%T,%T)',array(Base_UserCommon::get_my_user_id(),$now,$now));
	$_SESSION['base_login_audit'] = DB::Insert_ID('base_login_audit');
	$_SESSION['base_login_audit_user'] = Acl::get_user();
}
?>