<?php
/**
 * Tasks
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package utils-tasks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TasksInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('utils_tasks_task','
			id I4 AUTO KEY,
			title C(256) NOTNULL,
			description X,
			priority I1 DEFAULT 0,
			deadline D,
			status I1 DEFAULT 0,
			created_by I4 NOTNULL,
			created_on T NOTNULL,
			edited_by I4,
			edited_on T,
			longterm I1 DEFAULT 0,
			page_id C(32) NOTNULL,
			parent_module C(32) NOTNULL,
			permission I1 DEFAULT 0',
			array('constraints'=>', FOREIGN KEY (created_by) REFERENCES user_login(ID), FOREIGN KEY (edited_by) REFERENCES user_login(ID)'));
		if(!$ret){
			print('Unable to create table utils_tasks_task.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_tasks_assigned_contacts','
			task_id I4 NOTNULL,
			viewed I1 DEFAULT 0,
			contact_id I4 NOTNULL',
			array('constraints'=>', FOREIGN KEY (task_id) REFERENCES utils_tasks_task(id), FOREIGN KEY (contact_id) REFERENCES contact(ID)'));
		if(!$ret){
			print('Unable to create table utils_tasks_assigned_contacts.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_tasks_related_contacts','
			task_id I4 NOTNULL,
			contact_id I4 NOTNULL',
			array('constraints'=>', FOREIGN KEY (task_id) REFERENCES utils_tasks_task(id), FOREIGN KEY (contact_id) REFERENCES contact(ID), FOREIGN KEY (task_id) REFERENCES utils_tasks_task(id), FOREIGN KEY (contact_id) REFERENCES contact(ID)'));
		if(!$ret){
			print('Unable to create table utils_tasks_related_contacts.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme('Utils/Tasks');
		return $ret;
	}
	
	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('utils_tasks_related_contacts');
		$ret &= DB::DropTable('utils_tasks_assigned_contacts');
		$ret &= DB::DropTable('utils_tasks_task');
		Base_ThemeCommon::uninstall_default_theme('Utils/Tasks');
		return $ret;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/ActionBar','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Utils/PopupCalendar','version'=>0),
			array('name'=>'CRM/Contacts','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Tasks',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>