<?php
/**
 * Regional settings like currency, time...
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package base-regionalsettings
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_RegionalSettingsInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/Lang/Administrator','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Regional settings like currency, time...',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>