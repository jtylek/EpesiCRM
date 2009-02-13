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
		Base_LangCommon::install_translations($this -> get_type());
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
			array('name'=>'Base/Lang','version' => 0),
			array('name'=>'Base/Theme','version' => 0),
			array('name'=>'Utils/BBCode','version' => 0),
			array('name'=>'Base/Dashboard','version' => 0));
	}

	public static function info() {
		return array(
			'Description'=>'Simple Weather applet',
			'Author'=>'msteczkiewicz@telaxus.com',
			'License'=>'MIT'
		);
	}

	public static function simple_setup() {
		return true;
	}
}

?>
