<?php
/**
 * User_AdministratorInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package tcms-base-extra
 * @subpackage user-administrator
 */

class Base_User_AdministratorInstall extends ModuleInstall {
	public static function version() {
		return 0;
	}
	
	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
}

?>
