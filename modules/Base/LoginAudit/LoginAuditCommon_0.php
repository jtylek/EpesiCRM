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
if(!defined('LOGGED')) {
	$now=DB::DBTimeStamp(date("Y-m-d H:i:s",time()));
	@DB::Execute('INSERT INTO login_audit(user_login_id,timestamp) VALUES('.Base_UserCommon::get_my_user_id().','.$now.')');
	define('LOGGED','YES');
}
?>