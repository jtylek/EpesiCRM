<?php
/**
 * Testing flash charts
 * @author shacky@poczta.fm
 * @copyright shacky@poczta.fm
 * @license EPL
 * @version 0.1
 * @package tests-openflashchart
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
			'License'=>'SPL');
	}
}

?>