<?php
/**
 * @author Janusz Tylek <j@epe.si> and Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-Utils
 * @subpackage PopupCalendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_PopupCalendarInstall extends ModuleInstall {

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
			array('name'=>Base_ThemeInstall::module_name(), 'version'=>0),
			array('name'=>Base_LangInstall::module_name(), 'version'=>0),
			array('name'=>Base_User_SettingsInstall::module_name(), 'version'=>0),
			array('name'=>Utils_TooltipInstall::module_name(), 'version'=>0),
			array('name'=>Libs_LeightboxInstall::module_name(), 'version'=>0),
			array('name'=>Libs_QuickFormInstall::module_name(), 'version'=>0)
		);
	}	
	
}

?>