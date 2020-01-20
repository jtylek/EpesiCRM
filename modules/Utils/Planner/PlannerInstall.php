<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-utils
 * @subpackage planner
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_PlannerInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme('Utils/Planner');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/Planner');
		return true;
	}

	public function version() {
		return array('1.0');
	}	

	public function requires($v) {
		return array(
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Utils_TooltipInstall::module_name(),'version'=>0),
			array('name'=>Base_RegionalSettingsInstall::module_name(),'version'=>0),
			array('name'=>Base_User_SettingsInstall::module_name(),'version'=>0),
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0));
	}
}

?>