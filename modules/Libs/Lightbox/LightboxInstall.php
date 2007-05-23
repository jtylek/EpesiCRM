<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_LightboxInstall extends ModuleInstall {

	public static function install() {
		Base_ThemeCommon::install_default_theme('Libs/Lightbox');
		return true;
	}
	
	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Libs/Lightbox');
		return true;
	}
	public static function version() {
		return array("2.03.3");
	}
	
}

?>