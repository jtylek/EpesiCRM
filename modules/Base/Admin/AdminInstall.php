<?php
/**
 * Admin class.
 * 
 * This class provides administration module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage admin
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AdminInstall extends ModuleInstall {
	public static function install() {
		Base_ThemeCommon::install_default_theme('Base/Admin','theme1');
		return true;
	}
	
	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Base/Admin');
		return true;
	}
	
	public static function upgrade_1() {
		Base_ThemeCommon::install_default_theme('Base/Admin','theme1');
		return true;
	}

	public static function downgrade_1() {
		Base_ThemeCommon::install_default_theme('Base/Admin');
		return true;
	}
	
	public static function version() {
		return array('1.0.0','1.0.1');
	}
}

?>
