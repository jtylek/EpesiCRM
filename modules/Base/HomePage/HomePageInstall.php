<?php
/**
 * HomePageInit class.
 * 
 * This class provides initialization data for HomePage module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage homepage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_HomePageInstall extends ModuleInstall {
	public static function install() {
		$ret = DB::CreateTable('home_page',"user_login_id I KEY,url X2 NOTNULL",array('constraints' => ', FOREIGN KEY (user_login_id) REFERENCES user_login(id)'));
		if($ret===false) {
			print('Invalid SQL query - homepage install');
			return false;
		}
		return true;
	}
	
	public static function uninstall() {
		return DB::DropTable('home_page');
	}
	
	public static function version() {
		return array('0.8.9');
	}

	public static function requires($v) {
		return array(array('name'=>'Base/Box','version'=>0), 
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/User', 'version'=>0),
			array('name'=>'Base/ActionBar', 'version'=>0)
			);
	}
	
}

?>
