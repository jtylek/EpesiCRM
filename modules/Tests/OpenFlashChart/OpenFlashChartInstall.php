<?php
/**
 * Testing flash charts
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
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
			array('name'=>Libs_OpenFlashChartInstall::module_name(),'version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Testing flash charts',
			'Author'=>'shacky@poczta.fm',
			'License'=>'MIT');
	}
}

?>