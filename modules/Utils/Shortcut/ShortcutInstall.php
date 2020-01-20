<?php
/** 
 * @author Janusz Tylek <j@epe.si> and Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek 
 * @version 1.9.0
 * @license MIT 
 * @package epesi-utils 
 * @subpackage shortcut
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_ShortcutInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(
			array('name'=>Base_User_SettingsInstall::module_name(), 'version'=>0)
		    );
	}
}

?>
