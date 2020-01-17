<?php
/**
 * @author j@epe.si
 * @copyright 2008 Janusz Tylek
 * @license MIT
 * @version 1.2
 * @package epesi-applets
 * @subpackage google
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_GoogleInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this -> get_type());
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this -> get_type());
		return true;
	}

	public function version() {
		return array("1.4");
	}

	public function requires($v) {
		return array(
			array('name' => Base_ThemeInstall::module_name(), 'version' => 0),
			array('name' => Base_DashboardInstall::module_name(), 'version' => 0));
	}

	public static function info() {
		return array(
			'Description' => 'Simple Google Search applet',
			'Author' => 'j@epe.si',
			'License' => 'MIT');
	}

	public static function simple_setup() {
        return array('package'=>__('EPESI Core'), 'option'=>__('Additional applets'));
	}

}

?>
