<?php
/**
 * @author Kuba Sławiński
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
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