<?php
/**
 * Example event module
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ProjectPlanner_EmployeeEventInstall extends ModuleInstall {

	public function install() {
		Variable::set('CRM_ProjectsPlanner__start_day','9:00');
		Variable::set('CRM_ProjectsPlanner__end_day','17:00');
		Base_ThemeCommon::install_default_theme('CRM/ProjectPlanner/EmployeeEvent');
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/ProjectPlanner/EmployeeEvent');
		Variable::delete('CRM_ProjectsPlanner__start_day');
		Variable::delete('CRM_ProjectsPlanner__end_day');
		return true;
	}

	public function version() {
		return array('0.1');
	}

	public function requires($v) {
		return array(
//				array('name'=>'Utils/PopupCalendar','version'=>0),
//				array('name'=>'Utils/Attachment','version'=>0),
//				array('name'=>'Utils/Messenger','version'=>0),
				array('name'=>'Base/Lang','version'=>0),
				array('name'=>'CRM/Contacts','version'=>0),
				array('name'=>'Libs/QuickForm','version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'Employee event module',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}

	public static function simple_setup() {
		return false;
	}

}

?>
