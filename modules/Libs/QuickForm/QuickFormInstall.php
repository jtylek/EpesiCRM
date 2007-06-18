<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-libs
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package epesi-libs
 * @subpackage QuickForm
 */
class Libs_QuickFormInstall extends ModuleInstall {
	public static function install() {
		Base_ThemeCommon::install_default_theme('Libs/QuickForm');
		return true;
	}

	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Libs/QuickForm');
		return true;
	}

	public static function version() {
		return array('3.2.7');
	}
}

?>
