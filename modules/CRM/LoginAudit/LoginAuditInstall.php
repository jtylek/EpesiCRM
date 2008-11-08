<?php
/**
 * Provides login audit log
 * @author pbukowski@telaxus.com & jtylek@telaxus.com
 * @copyright pbukowski@telaxus.com & jtylek@telaxus.com
 * @license EPL
 * @version 0.1
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_LoginAuditInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme($this -> get_type());
		$ret = DB::CreateTable('base_login_audit',"id I AUTO KEY, user_login_id I, start_time T, end_time T, ip_address C(32), host_name C(64)");
		if($ret===false){
			die('Invalid SQL query - Database module (login_audit table)');
		}
		return true;
	}

	public function uninstall() {
		return DB::DropTable('base_login_audit');
		Base_ThemeCommon::uninstall_default_theme($this -> get_type());
		return true;
	}

	public function version() {
		return array("0.6");
	}

	public function requires($v) {
		return array(
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'CRM/Contacts', 'version'=>0),
			array('name'=>'Base/User', 'version'=>0));
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:jtylek@telaxus.com">Janusz Tylek</a> and <a href="mailto:pbukowski@telaxus.com">Pawel Bukowski</a>
					 (<a href="http://www.telaxus.com">Telaxus LLC</a>)',
					 'License'=>'TL', 'Description'=>'Provides login audit log.');
	}

	public static function simple_setup() {
		return true;
	}

}

?>
