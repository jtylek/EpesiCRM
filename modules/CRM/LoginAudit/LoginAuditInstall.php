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

class CRM_LoginAuditInstall extends ModuleInstall {

	public function install() {
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
		return array("1.0");
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
					 'License'=>'MIT', 'Description'=>'Provides login audit log.');
	}

	public static function simple_setup() {
		return 'CRM';
	}

}

?>
