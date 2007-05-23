<?php
/**
 * MainModuleIndicatorInstall class.
 * 
 * This class provides initialization data for MainModuleIndicator module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for MainModuleIndicator module.
 * @package tcms-base-extra
 * @subpackage MainModuleIndicator
 */
class Base_MainModuleIndicatorInstall extends ModuleInstall {
	public static function install() {
		Base_ThemeCommon::install_default_theme('Base/MainModuleIndicator');
		return true;
	}
	
	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Base/MainModuleIndicator');
		return true;
	}
	
	public static function version() {
		return array('1.0');
	}
}

?>
