<?php
/**
 * Shows who is logged to epesi.
 * 
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-tools
 * @subpackage WhoIsOnline
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_WhoIsOnlineInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('tools_whoisonline_users','
			session_name C(128) KEY NOTNULL,
			user_login_id I4 NOTNULL',
			array('constraints'=>', FOREIGN KEY (user_login_id) REFERENCES user_login(ID)'));
		if(!$ret){
			print('Unable to create table tools_whoisonline_users.<br>');
			return false;
		}
		return $ret;
	}
	
	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('tools_whoisonline_users');
		return $ret;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Base_UserInstall::module_name(),'version'=>0),
			array('name'=>Base_User_SettingsInstall::module_name(),'version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Shows who is logged to epesi.',
			'Author'=>'j@epe.si',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return __('EPESI Core');
	}
	
}

?>