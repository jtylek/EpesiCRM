<?php
/**
 * 
 * @author Kuba Sławiński
 * @copyright Kuba Sławiński
 * @license EPL
 * @version 0.1
 * @package tests-colorpicker
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
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Libs/ScriptAculoUs','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'Kuba Sławiński',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>