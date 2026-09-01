<?php
/**
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage calendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_CalendarInstall extends ModuleInstall {

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
		return array(
			array('name'=>Utils_CalendarInstall::module_name(),'version'=>0),
			array('name'=>'Tests/Calendar/Event','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>