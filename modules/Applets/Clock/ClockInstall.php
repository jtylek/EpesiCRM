<?php
/**
 * Flash clock
 * (clock taken from http://www.kirupa.com/developer/actionscript/clock.htm)
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-clock
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_ClockInstall extends ModuleInstall {

	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("0.9.9");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/Dashboard','version'=>0),
			array('name'=>'Base/RegionalSettings','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Flash clock',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>