<?php
/**
 * Displays busy report of employees
 * @author j@epe.si
 * @copyright Janusz Tylek
 * @license Commercial
 * @version 0.1
 * @package epesi-Utils
 * @subpackage CalendarBusyReport
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CalendarBusyReportInstall extends ModuleInstall {

	public function install() {
        Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}
	
	public function uninstall() {
        Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base','version'=>0),
			array('name'=>Utils_CalendarBusyReport_EventInstall::module_name(),'version'=>0),
			array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Utility module to display calendar busy report',
			'Author'=>'j@epe.si',
			'License'=>'Commercial');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>