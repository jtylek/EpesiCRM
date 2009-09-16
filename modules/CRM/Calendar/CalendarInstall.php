<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_CalendarInstall extends ModuleInstall {
	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme('CRM/Calendar');
		$this->add_aco('manage others','Employee Manager');
		$this->add_aco('access','Employee');
		Utils_WatchdogCommon::register_category('crm_calendar', array('CRM_CalendarCommon','watchdog_label'));
		DB::CreateTable('crm_calendar_custom_events_handlers',
						'id I4 AUTO KEY,'.
						'group_name C(64),'.
						'get_callback C(128),'.
						'get_all_callback C(128),'.
						'delete_callback C(128),'.
						'update_callback C(128)',
						array('constraints'=>''));
		return true;
	}

	public function uninstall() {
		DB::DropTable('crm_calendar_custom_events_handlers');
		Base_ThemeCommon::uninstall_default_theme('CRM/Calendar');
		Utils_WatchdogCommon::unregister_category('crm_calendar');
		return true;
	}

	public function info() {
		return array('Author'=>'<a href="http://www.telaxus.com">Telaxus LLC</a>', 'Licence'=>'TL', 'Description'=>'Simple calendar and organiser.');
	}

	public function simple_setup() {
		return true;
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Utils/Calendar','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0),
			array('name'=>'Base/RegionalSettings','version'=>0),
			array('name'=>'CRM/Filters','version'=>0),
			array('name'=>'CRM/Calendar/Event','version'=>0),
			array('name'=>'Utils/Watchdog','version'=>0)
		);
	}
	public function version() {
		return array('0.1.0');
	}
}

?>
