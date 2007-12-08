<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Utils_SidetipInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('CRM/Calendar/Utils/Sidetip');
		return true;
	}
	
	public function requires($v) {
		return array();
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Calendar/Utils/Sidetip');
		return true;
	}
}

?>
