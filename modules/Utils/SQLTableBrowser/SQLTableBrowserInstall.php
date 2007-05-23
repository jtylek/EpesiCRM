<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_SQLTableBrowserInstall extends ModuleInstall {

	public static function install() {
		Base_ThemeCommon::install_default_theme('Utils/SQLTableBrowser');
		return true;
	}
	
	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/SQLTableBrowser');
		return true;
	}
	
}

?>