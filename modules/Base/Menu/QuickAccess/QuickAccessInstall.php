<?php
/**
 * QuickAccessInstall class.
 * 
 * This class provides initialization data for QuickAccess module.
 * 
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage menu-quickaccess
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Menu_QuickAccessInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(array('name'=>Base_LangInstall::module_name(),'version'=>0),
				array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0),
				array('name'=>Base_MenuInstall::module_name(),'version'=>0),
				array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
				array('name'=>Base_AclInstall::module_name(),'version'=>0));
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>
