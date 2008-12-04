<?php
/**
 * Simple reports for CRM Calendar
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar-reports
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_ReportsInstall extends ModuleInstall {

	public function install() {
		$this->add_aco('access','Employee');
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'CRM/Calendar/Event','version'=>0),
			array('name'=>'Libs/OpenFlashChart','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Utils/PopupCalendar','version'=>0),
			array('name'=>'Utils/TabbedBrowser','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Simple reports for CRM Calendar',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>