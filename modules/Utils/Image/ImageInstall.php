<?php
/**
 * Utils_ImageInstall class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_ImageInstall extends ModuleInstall {
	public static function install() {
		Base_ThemeCommon::install_default_theme('Utils/Image');
		return true;
	}
	
	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/Image');
		return true;
	}
	
	public static function version() {
		return array('0.8.9');
	}
}

?>
