<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-tests
 * @subpackage Report
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_ReportInstall extends ModuleInstall {

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
			array('name'=>'Utils/RecordBrowser/Reports','version'=>0),
			array('name'=>CRM_ContactsInstall::module_name(),'version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'Arkadiusz Bisaga, Janusz Tylek',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>