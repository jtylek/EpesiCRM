<?php
/**
 * Example event module
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-crm
 * @subpackage calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_EventInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme(CRM_Calendar_EventInstall::module_name());
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(CRM_Calendar_EventInstall::module_name());
		Utils_MessengerCommon::delete_by_parent_module($this->get_type());
		return true;
	}

	public function version() {
		return array('1.0');
	}

	public function requires($v) {
		return array(
				array('name'=>CRM_CommonInstall::module_name(), 'version'=>0),
				array('name'=>Base_LangInstall::module_name(), 'version'=>0),
				array('name'=>Utils_Calendar_EventInstall::module_name(),'version'=>0),
				array('name'=>Utils_PopupCalendarInstall::module_name(),'version'=>0),
				array('name'=>Utils_AttachmentInstall::module_name(),'version'=>0),
				array('name'=>Utils_MessengerInstall::module_name(),'version'=>0),
				array('name'=>CRM_ContactsInstall::module_name(),'version'=>0),
				array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0),
				array('name'=>Libs_TCPDFInstall::module_name(),'version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'CRM event module',
			'Author'=>'j@epe.si',
			'License'=>'MIT');
	}

	public static function simple_setup() {
		return 'CRM';
	}

}

?>
