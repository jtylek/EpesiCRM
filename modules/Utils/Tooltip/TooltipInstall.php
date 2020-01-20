<?php
/** 
 * @author Janusz Tylek <j@epe.si> and Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek 
 * @version 1.9.0
 * @license MIT 
 * @package epesi-utils 
 * @subpackage tooltip
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TooltipInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme(Utils_TooltipInstall::module_name());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(Utils_TooltipInstall::module_name());
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(
			array('name'=>Base_ThemeInstall::module_name(), 'version'=>0),
			array('name'=>Base_User_SettingsInstall::module_name(), 'version'=>0)
		    );
	}
}

?>
