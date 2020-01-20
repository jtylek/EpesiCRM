<?php
/**
 * User_AdministratorInstall class.
 * 
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage user-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_AdministratorInstall extends ModuleInstall {
	public function version() {
		return array('1.0.0');
	}
	
	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	public function requires($v) {
		return array(
			array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0),
			array('name'=>Base_AdminInstall::module_name(),'version'=>0),
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Base_AclInstall::module_name(),'version'=>0),
			array('name'=>Utils_GenericBrowserInstall::module_name(),'version'=>0),
			array('name'=>Base_UserInstall::module_name(),'version'=>0),
			array('name'=>Base_ActionBarInstall::module_name(),'version'=>0),
			array('name'=>Base_User_LoginInstall::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0));
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>
