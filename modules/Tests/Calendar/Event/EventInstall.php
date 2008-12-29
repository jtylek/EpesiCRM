<?php
/**
 * Example event module
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tests
 * @subpackage calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Calendar_EventInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable("tests_calendar_event",
			"id I AUTO KEY," .

			"title C(64) NO NULL, " .
			"description X, " .

			"start I4 NOT NULL, " .
			"duration I4 NOT NULL, " .
			"timeless I DEFAULT 0, " .

			"color C(16), " .

			"created_on T NOT NULL," .
			"created_by I4 REFERENCES user_login(id)," .
			"edited_on T," .
			"edited_by I4 REFERENCES user_login(id)"
		);
		if(!$ret) {
			print('Unable to create tests_calendar_event table');
			return false;
		}
		return $ret;
	}

	public function uninstall() {
		return DB::DropTable('tests_calendar_event');
	}

	public function version() {
		return array("0.1");
	}

	public function requires($v) {
		return array(
				array('name'=>'Utils/Calendar/Event','version'=>0),
				array('name'=>'Utils/PopupCalendar','version'=>0),
				array('name'=>'Libs/QuickForm','version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'Example event module',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}

	public static function simple_setup() {
		return false;
	}

}

?>
