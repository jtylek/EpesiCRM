<?php
/**
 * Navigation component: back, refresh, forward.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage navigation
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_NavigationInstall extends ModuleInstall {
	public static function install() {
		Base_ThemeCommon::install_default_theme('Base/Navigation');
		return true;
	}
	
	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Base/Navigation');
		return true;
	}
	
	public static function version() {
		return array('1.0.0');
	}
	public static function requires($v) {
		return array(array('name'=>'Base/Theme','version'=>0));
	}
}

?>

