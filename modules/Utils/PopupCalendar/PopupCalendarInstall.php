<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CalendarInstall extends ModuleInstall {

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
			array('name'=>'Base/Theme', 'version'=>0),
			array('name'=>'Utils/Tooltip', 'version'=>0),
			array('name'=>'Libs/Leightbox', 'version'=>0),
			array('name'=>'Libs/QuickForm', 'version'=>0)
		);
	}	
	
	public function provides($v) {
		return array();
	}
}

?>