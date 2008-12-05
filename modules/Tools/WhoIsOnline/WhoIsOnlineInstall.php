<?php
/**
 * Shows who is logged to epesi.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tools
 * @subpackage WhoIsOnline
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_WhoIsOnlineInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		$ret = true;
		$ret &= DB::CreateTable('tools_whoisonline_users','
			session_name C(32) KEY NOTNULL,
			user_login_id I4 NOTNULL',
			array('constraints'=>', FOREIGN KEY (user_login_id) REFERENCES user_login(ID)'));
		if(!$ret){
			print('Unable to create table tools_whoisonline_users.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme($this -> get_type());
		return $ret;
	}
	
	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('tools_whoisonline_users');
		Base_ThemeCommon::uninstall_default_theme($this -> get_type());
		return $ret;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/User','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Shows who is logged to epesi.',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>