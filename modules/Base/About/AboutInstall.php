<?php
/**
 * About Epesi
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package base-about
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AboutInstall extends ModuleInstall {

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
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Utils/Tooltip','version'=>0),
			array('name'=>'Libs/Leightbox','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'About Epesi',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>