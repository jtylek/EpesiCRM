<?php
/**
 * Gets host ip or domain
 * @author j@epe.si
 * @copyright 2008 Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-applets
 * @subpackage host
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_HostInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this -> get_type());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this -> get_type());
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0),
			array('name'=>Base_DashboardInstall::module_name(),'version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Gets host ip or domain',
			'Author'=>'j@epe.si',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
        return array('package'=>__('EPESI Core'), 'option'=>__('Additional applets'));
	}
	
}

?>