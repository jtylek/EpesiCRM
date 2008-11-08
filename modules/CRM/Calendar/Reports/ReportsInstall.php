<?php
/**
 * Simple reports for CRM Calendar
 * @author shacky@poczta.fm
 * @copyright shacky@poczta.fm
 * @license EPL
 * @version 0.1
 * @package crm-calendar--reports
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
		return array("0.1");
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
			'Author'=>'shacky@poczta.fm',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>