<?php
/** 
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @version 0.1
 * @package apps-activeboard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ActiveBoardInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('apps_activeboard_applets','
			id I4 AUTO KEY,
			base_user_login_id I4,
			module C(128)',
			array('constraints'=>', FOREIGN KEY (base_user_login_id) REFERENCES user_login(ID), FOREIGN KEY (module) REFERENCES modules(NAME)'));
		if(!$ret){
			print('Unable to create table apps_activeboard_applets.<br>');
			return false;
		}
		return $ret;
	}
	
	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('apps_activeboard_applets');
		return $ret;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(array('name'=>'Base_User_Settings','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Something like igoogle',
			'Author'=>'Paul Bukowski <pbukowski@telaxus.com>',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>