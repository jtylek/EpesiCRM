<?php
/**
 * ActionBar
 * 
 * This class provides action bar component.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @package epesi-base-extra
 * @subpackage actionbar
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBarInstall extends ModuleInstall {

	public static function install() {
		Base_ThemeCommon::install_default_theme('Base/ActionBar');
		return true;
	}
	
	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Base/ActionBar');
		return true;
	}
	
	public static function version() {
		return array("0.9.9");
	}

	public static function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0));
	}
	
}
?>