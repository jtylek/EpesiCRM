<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license EPL
 * @version 0.1
 * @package tests-codepress
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
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Utils/Calendar','version'=>0),
			array('name'=>'Tests/Calendar/Event','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'abisaga@telaxus.com',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>