<?php
/**
 * BoxInit class.
 * 
 * This class provides initialization of Box module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization of Box module.
 * @package epesi-base-extra
 * @subpackage box
 */
class Base_BoxInstall extends ModuleInstall {

	public static function install() {
		Base_ThemeCommon::install_default_theme('Base/Box');
		
		return true;
	}

	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Base/Box');

		return true;
	}
	
	public static function version() {
		return array('1.0.0');
	}
}
?>
