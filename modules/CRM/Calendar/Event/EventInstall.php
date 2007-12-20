<?php
/**
 * Example event module
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package tests-calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_EventInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('crm_calendar_group_emp',
			'id I AUTO KEY,'.
			'contact I4 NUT NULL',
			array('constraints'=>'')
			);
		$ret &= DB::CreateTable('crm_calendar_group_cus',
			'id I AUTO KEY,'.
			'contact I4 NUT NULL',
			array('constraints'=>'')
			);
		$ret &= DB::CreateTable('crm_calendar_event',
			'id I AUTO KEY,'.

			'title C(64) NOT NULL, '.
			'description X, '.

			'start I4 NOT NULL, '.
			'end I4 NOT NULL, '.
			'timeless I1 DEFAULT 0, '.

			'access I1 DEFAULT 0, '.
			'priority I1 DEFAULT 0, '.

			'created_on T NOT NULL,'.
			'created_by I4,'.
			'edited_on T DEFAULT 0,'.
			'edited_by I4 DEFAULT -1',
			array('constraints'=>	', FOREIGN KEY (created_by) REFERENCES user_login(id)'.
									', FOREIGN KEY (edited_by) REFERENCES user_login(id)')
		);
		if(!$ret) {
			print('Unable to create crm_calendar_event table');
			return false;
		}
		Base_ThemeCommon::install_default_theme('CRM/Calendar/Event');
		return $ret;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Calendar/Event');
		$ret = DB::DropTable('crm_calendar_event');
		$ret &= DB::DropTable('crm_calendar_group_emp');
		$ret &= DB::DropTable('crm_calendar_group_cus');
		return $ret;
	}
	
	public function version() {
		return array('0.1');
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
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>