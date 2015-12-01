<?php
/**
 * @author msteczkiewicz@telaxus.com
 * @copyright 2009 Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-applets
 * @subpackage
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_WeatherInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this -> get_type());
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this -> get_type());
		return true;
	}

	public function version() {
		return array("0.3");
	}

	public function requires($v) {
		return array(
			array('name'=>Base_LangInstall::module_name(),'version' => 0),
			array('name'=>Base_ThemeInstall::module_name(),'version' => 0),
			array('name'=>Utils_BBCodeInstall::module_name(),'version' => 0),
			array('name'=>Base_DashboardInstall::module_name(),'version' => 0));
	}

	public static function info() {
		return array(
			'Description'=>'Simple Weather applet',
			'Author'=>'msteczkiewicz@telaxus.com',
			'License'=>'MIT'
		);
	}

	public static function simple_setup() {
        return array('package'=>__('EPESI Core'), 'option'=>__('Additional applets'));
	}
}

?>
