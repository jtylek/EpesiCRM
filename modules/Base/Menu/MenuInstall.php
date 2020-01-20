<?php
/**
 * MenuInstall class.
 * 
 * This class provides initialization data for Menu module.
 * 
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage menu
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MenuInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme(Base_MenuInstall::module_name());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(Base_MenuInstall::module_name());
		return true;
	}
	
	public function version () {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(
//			array('name'=>Base_Menu_QuickAccessInstall::getName(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Utils_MenuInstall::module_name(),'version'=>0)
		);
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>
