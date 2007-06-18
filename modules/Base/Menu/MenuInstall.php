<?php
/**
 * MenuInstall class.
 * 
 * This class provides initialization data for Menu module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Menu module.
 * @package epesi-base-extra
 * @subpackage menu
 */
class Base_MenuInstall extends ModuleInstall {
	public static function install() {
		Base_ThemeCommon::install_default_theme('Base/Menu');
		return true;
	}
	
	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Base/Menu');
		return true;
	}
	
	public static function version () {
		return array('1.0.0');
	}
}

?>
