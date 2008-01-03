<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_CalendarInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('CRM/Calendar');
		$this->add_aco('filters','Employee Manager');
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Calendar');
		return $true;
	}

	public function provides($v) {
		return array();
	}

	public function info() {
		return array('Author'=>'<a href="http://www.telaxus.com">Telaxus LLC</a>', 'Licence'=>'TL', 'Description'=>'Simple calendar and organiser.');
	}

	public function simple_setup() {
		return true;
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Utils/Calendar','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0),
			array('name'=>'Base/RegionalSettings','version'=>0),
			array('name'=>'CRM/Filters','version'=>0),
			array('name'=>'CRM/Calendar/Meeting','version'=>0)
		);
	}
	public function version() {
		return array('0.1.0');
	}
}

?>
