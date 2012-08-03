<?php
/**
 * HomePageInit class.
 * 
 * This class provides initialization data for HomePage module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage homepage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_HomePageInstall extends ModuleInstall {
	public function install() {
		$ret = DB::CreateTable('home_page',"user_login_id I KEY,url X2 NOTNULL",array('constraints' => ', FOREIGN KEY (user_login_id) REFERENCES user_login(id)'));
		if($ret===false) {
			print('Invalid SQL query - homepage install');
			return false;
		}
		return true;
	}
	
	public function uninstall() {
		return DB::DropTable('home_page');
	}
	
	public function version() {
		return array('1.0');
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}

	public function requires($v) {
		return array(array('name'=>'Base/Box','version'=>0), 
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Utils/Shortcut', 'version'=>0), 
			array('name'=>'Base/User', 'version'=>0),
			array('name'=>'Base/ActionBar', 'version'=>0)
			);
	}
	
}

?>
