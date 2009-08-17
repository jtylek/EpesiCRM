<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
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
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Utils/Tooltip','version'=>0), 
			array('name'=>'Base/RegionalSettings','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0),
			array('name'=>'Base/Theme','version'=>0));
	}
}

?>