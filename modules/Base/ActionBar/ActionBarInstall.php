<?php
/**
 * ActionBar
 * 
 * This class provides action bar component.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage actionbar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBarInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(Base_ActionBarInstall::module_name());
		return true;
	}
	
	public function version() {
		return array("1.0");
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}

	public function requires($v) {
		return array(
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Libs_LeightboxInstall::module_name(),'version'=>0),
			array('name'=>Base_User_SettingsInstall::module_name(),'version'=>0),
			array('name'=>Base_Menu_QuickAccessInstall::module_name(),'version'=>0),
			array('name'=>Utils_TooltipInstall::module_name(),'version'=>0),
			array('name'=>Base_User_SettingsInstall::module_name(),'version'=>0));
	}
	
}
?>