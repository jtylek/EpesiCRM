<?php
/**
 * Provides login audit log
 * @author Paul Bukowski <pbukowski@telaxus.com> & Janusz Tylek <jtylek@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage loginaudit
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
