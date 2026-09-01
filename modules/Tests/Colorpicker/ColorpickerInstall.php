<?php
/**
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage colorpicker
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_ColorpickerInstall extends ModuleInstall {

	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("2.0");
	}
	
	public function requires($v) {
		return array();
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'Kuba Sławiński',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>