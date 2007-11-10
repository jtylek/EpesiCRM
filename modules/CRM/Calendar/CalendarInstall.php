<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_CalendarInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('CRM/Calendar');
		$ret = true;
		return $ret;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Calendar');
		$ret = true;
		
		return $ret;
	}
	
	public function provides($v) {
		return array();
	}
	
	public function info() {
		return array('Author'=>'<a href="mailto:kslawinski@telaxus.com">Kuba Sławiński</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'Licence'=>'TL', 'Description'=>'Simple calendar and organiser.');
	}
	
	public function simple_setup() {
		return true;
	}
	public function requires($v) {
		return array(
			array('name'=>'Utils/TabbedBrowser', 'version'=>0),
			array('name'=>'Utils/GenericBrowser', 'version'=>0),
			array('name'=>'Utils/Calendar', 'version'=>0),
			array('name'=>'Utils/Tooltip', 'version'=>0),
			
			array('name'=>'CRM/Calendar/Utils/Func', 'version'=>0),
			array('name'=>'CRM/Calendar/Utils/Sidetip', 'version'=>0),
			
			array('name'=>'CRM/Calendar/Event', 'version'=>0),
			array('name'=>'CRM/Calendar/Event/Personal', 'version'=>0),
			
			array('name'=>'CRM/Calendar/View/Agenda', 'version'=>0),
			array('name'=>'CRM/Calendar/View/Week', 'version'=>0),
			array('name'=>'CRM/Calendar/View/Month', 'version'=>0),
			array('name'=>'CRM/Calendar/View/Day', 'version'=>0),
			array('name'=>'CRM/Calendar/View/Year', 'version'=>0),
			
			array('name'=>'CRM/Contacts', 'version'=>0)
		);
	}
	public function version() {
		return array('0.1.0');
	}
}

?>
