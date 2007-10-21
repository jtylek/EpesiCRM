<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Utils_MiniCalendarInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme('CRM/Calendar/Utils/MiniCalendar');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Calendar/Utils/MiniCalendar');
		return true;
	}
	
	public function requires($v) {
		return array();
	}	
	
	public function provides($v) {
		return array();
	}
}

?>