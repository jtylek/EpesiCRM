<?php
/**
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-projectplanner
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ProjectPlannerInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('crm_projectplanner_work','
			id I4 AUTO KEY,
			employee_id I4 NOTNULL,
			project_id I4 NOTNULL,
			start I4 NOTNULL,
			end I4 NOTNULL',
			array('constraints'=>', FOREIGN KEY (employee_id) REFERENCES contact(ID), FOREIGN KEY (project_id) REFERENCES projects(id)'));
		if(!$ret){
			print('Unable to create table crm_projectplanner_employee_work.<br>');
			return false;
		}
		return $ret;
	}

	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('crm_projectplanner_work');
		return $ret;
	}

	public function version() {
		return array("0.1");
	}

	public function requires($v) {
		return array(
			array('name'=>'Apps/Projects','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'CRM/Contacts','version'=>0),
			array('name'=>'CRM/ProjectPlanner/EmployeeEvent','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Libs/ScriptAculoUs','version'=>0),
			array('name'=>'Utils/Calendar','version'=>0),
			array('name'=>'Utils/Calendar/Event','version'=>0),
			array('name'=>'Utils/TabbedBrowser','version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}

	public static function simple_setup() {
		return true;
	}

}

?>
