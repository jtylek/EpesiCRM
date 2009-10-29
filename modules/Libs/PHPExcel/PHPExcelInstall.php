<?php
/**
 * Excel import/export library
 * @author shacky@poczta.fm
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Libs
 * @subpackage PHPExcel
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_PHPExcelInstall extends ModuleInstall {

	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("1.7.0");
	}
	
	public function requires($v) {
		return array();
	}
	
	public static function info() {
		return array(
			'Description'=>'Excel import/export library',
			'Author'=>'shacky@poczta.fm',
			'License'=>'LGPL');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>