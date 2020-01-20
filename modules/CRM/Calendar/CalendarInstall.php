<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-crm
 * @subpackage calendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_CalendarInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme(CRM_CalendarInstall::module_name());
		Base_AclCommon::add_permission(_M('Calendar'),array('ACCESS:employee'));
		DB::CreateTable('crm_calendar_custom_events_handlers',
						'id I4 AUTO KEY,'.
						'group_name C(64),'.
						'handler_callback C(128)',
						array('constraints'=>''));
		return true;
	}

	public function uninstall() {
		Base_AclCommon::delete_permission('Calendar');
		DB::DropTable('crm_calendar_custom_events_handlers');
		Base_ThemeCommon::uninstall_default_theme(CRM_CalendarInstall::module_name());
		return true;
	}

	public function info() {
		return array('Author'=>'<a href="https://epe.si">Janusz Tylek</a>', 'License'=>'TL', 'Description'=>'Simple calendar and organiser.');
	}

	public static function simple_setup() {
		return 'CRM';
	}
	
	public function requires($v) {
		return array(
			array('name'=>Base_LangInstall::module_name(), 'version'=>0),
			array('name'=>Utils_CalendarInstall::module_name(),'version'=>0),
			array('name'=>Base_User_SettingsInstall::module_name(),'version'=>0),
			array('name'=>Base_RegionalSettingsInstall::module_name(),'version'=>0),
			array('name'=>CRM_FiltersInstall::module_name(),'version'=>0),
			array('name'=>CRM_Calendar_EventInstall::module_name(),'version'=>0),
			array('name'=>Utils_WatchdogInstall::module_name(),'version'=>0),
			array('name'=>Utils_LeightboxPromptInstall::module_name(),'version'=>0)
		);
	}
	public function version() {
		return array('0.1.0');
	}
}

?>
