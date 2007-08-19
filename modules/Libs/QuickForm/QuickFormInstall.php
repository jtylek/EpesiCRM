<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-libs
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

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
	public static function requires_0() {
		return array(array('name'=>'Base/Theme','version'=>0));
	}
}

?>
