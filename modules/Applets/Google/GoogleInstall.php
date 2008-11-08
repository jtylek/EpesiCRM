<?php

/**
 *
 * @author msteczkiewicz@telaxus.com
 * @copyright msteczkiewicz@telaxus.com
 * @license SPL
 * @version 1.2
 * @package applets-google
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
		return array("1.2");
	}

	public function requires($v) {
		return array(
			array('name' => 'Base/Theme', 'version' => 0),
			array('name' => 'Base/Dashboard', 'version' => 0));
	}

	public static function info() {
		return array(
			'Description' => 'Simple Google Search applet',
			'Author' => 'msteczkiewicz@telaxus.com',
			'License' => 'SPL');
	}

	public static function simple_setup() {
		return true;
	}

}

?>
