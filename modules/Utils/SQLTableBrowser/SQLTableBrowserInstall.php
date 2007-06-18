<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.8
 * @licence SPL
 * @package epesi-utils
 */
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
	
	public static function version() {
		return array('0.8.0');
	}
}

?>