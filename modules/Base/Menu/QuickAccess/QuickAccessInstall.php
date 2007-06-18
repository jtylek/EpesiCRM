<?php
/**
 * QuickAccessInstall class.
 * 
 * This class provides initialization data for QuickAccess module.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for QuickAccess module.
 * @package epesi-base-extra
 * @subpackage menu-quick-access
 */

class Base_Menu_QuickAccessInstall extends ModuleInstall {
	public static function install() {
		$ret = DB::CreateTable('quick_access',"user_login_id I NOTNULL, label C(64) NOTNULL, link C(255) NOTNULL", array('constraints' => ', PRIMARY KEY (user_login_id,label)'));
		if($ret===false) {
			print('Invalid SQL query - Base/Menu/QuickAccess module install');
			return false;
		}
		return true;
	}
	
	public static function uninstall() {
		return DB::DropTable('quick_access');;
	}
	
	public static function version() {
		return array('1.0.0');
	}
}

?>
