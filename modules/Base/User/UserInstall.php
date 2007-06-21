<?php
/**
 * UserInstall class.
 * 
 * This class provides initialization data for User module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for User module.
 * @package epesi-base-extra
 * @subpackage user
 */
class Base_UserInstall extends ModuleInstall {
	public static function install() {
		$ret = DB::CreateTable('user_login',"id I AUTO KEY ,login C(32) NOTNULL, active I1 NOTNULL DEFAULT 1", array('constraints' => ', UNIQUE (login)'));
		if($ret===false) {
			print('Invalid SQL query - User module install');
			return false;
		}
		return true;
	}
	
	public static function uninstall() {
		return DB::DropTable('user_login');
	}
	
	public static function version() {
		return array("1.0");
	}
	
}
?>
