<?php
/**
 * Testing flash charts
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tests
 * @subpackage openflashchart
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_OpenFlashChartInstall extends ModuleInstall {

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
			array('name'=>'Libs/OpenFlashChart','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Testing flash charts',
			'Author'=>'shacky@poczta.fm',
			'License'=>'MIT');
	}
}

?>