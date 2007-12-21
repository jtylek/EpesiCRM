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

class CRM_LoginAuditCommon extends ModuleCommon {

	public static function admin_caption() {
		return 'Login Audit';
	}

	public static function body_access() {
		return Acl::is_user();
	}

	public static function init() {
		if(isset($_SESSION['base_login_audit']) && isset($_SESSION['base_login_audit_user']) && $_SESSION['base_login_audit_user']==Acl::get_user()) {
			DB::Execute('UPDATE base_login_audit SET end_time=%T WHERE id=%d',array(time(),$_SESSION['base_login_audit']));
		} elseif(Acl::is_user()) {
			$now = time();
			$remote_address = $_SERVER['REMOTE_ADDR'];
			$remote_host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
			DB::Execute('INSERT INTO base_login_audit(user_login_id,start_time,end_time,ip_address,host_name) VALUES(%d,%T,%T,%s,%s)',array(Acl::get_user(),$now,$now,$remote_address,$remote_host));
			$_SESSION['base_login_audit'] = DB::Insert_ID('base_login_audit','id');
			$_SESSION['base_login_audit_user'] = Acl::get_user();
		}
	}
}
on_init(array('CRM_LoginAuditCommon','init'));
?>
