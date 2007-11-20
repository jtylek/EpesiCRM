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

class Base_LoginAuditInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this -> get_type());
	
		
		$ret = DB::CreateTable('base_login_audit',"id I AUTO KEY, user_login_id I, start_time T, end_time T, ip_address C(32), host_name C(64)");
	if($ret===false){
		die('Invalid SQL query - Database module (login_audit table)');
	} else {
		$now = time();
		$remote_address = $_SERVER['REMOTE_ADDR'];
		$remote_host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
		DB::Execute('INSERT INTO base_login_audit(user_login_id,start_time,end_time,ip_address,host_name) VALUES(%d,%T,%T,%s,%s)',array(Base_UserCommon::get_my_user_id(),$now,$now,$remote_address,$remote_host));
		$_SESSION['base_login_audit'] = DB::Insert_ID('base_login_audit');
		$_SESSION['base_login_audit_user'] = Acl::get_user();
	}
		return true;
	}
	
	public function uninstall() {
		return DB::DropTable('base_login_audit');
		Base_ThemeCommon::uninstall_default_theme($this -> get_type());
		return true;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Tools/WhoIsOnline','version'=>0));
	}
	
		public static function info() {
		return array('Author'=>'<a href="mailto:jtylek@telaxus.com">Janusz Tylek</a> and <a href="mailto:pbukowski@telaxus.com">Pawel Bukowski</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Provides login audit log.');
	}
		
	public static function simple_setup() {
		return true;
	}
	
}

?>