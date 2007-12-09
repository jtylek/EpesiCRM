<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CalendarInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('Utils/Calendar');
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/Calendar');
		return true;
	}

	public function provides($v) {
		return array();
	}

	public function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a>, <a href="mailto:pbukowski@telaxus.com">Paul Bukowski</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'Licence'=>'TL', 'Description'=>'Abstract calendar.');
	}

	public function simple_setup() {
		return true;
	}
	public function requires($v) {
		return array(
			array('name'=>'Utils/TabbedBrowser', 'version'=>0),
			array('name'=>'Base/RegionalSettings', 'version'=>0),
			array('name'=>'Base/Theme', 'version'=>0),
			array('name'=>'Utils/PopupCalendar', 'version'=>0),
			array('name'=>'Base/Lang', 'version'=>0)
		);
	}
	public function version() {
		return array('0.1.0');
	}
}

?>
