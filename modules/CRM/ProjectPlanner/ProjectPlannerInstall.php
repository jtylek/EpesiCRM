<?php
/**
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package custom-projects-planner
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Custom_Projects_PlannerInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		Variable::set('Custom_Projects_Planner__start_day','9:00');
		Variable::set('Custom_Projects_Planner__end_day','17:00');
		$ret &= DB::CreateTable('custom_projects_planner_work','
			id I4 AUTO KEY,
			employee_id I4 NOTNULL,
			project_id I4,
			vacations I1,
			allday I1,
			start T NOTNULL,
			end T',
			array('constraints'=>', FOREIGN KEY (employee_id) REFERENCES contact(ID), FOREIGN KEY (project_id) REFERENCES custom_projects(id)'));
		if(!$ret){
			print('Unable to create table custom_projects_planner_employee_work.<br>');
			return false;
		}
		return $ret;
	}

	public function uninstall() {
		Variable::delete('Custom_Projects_Planner__start_day');
		Variable::delete('Custom_Projects_Planner__end_day');
		$ret = true;
		$ret &= DB::DropTable('custom_projects_planner_work');
		return $ret;
	}

	public function version() {
		return array("0.1");
	}

	public function requires($v) {
		return array(
			array('name'=>'Custom/Projects','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'CRM/Contacts','version'=>0),
			array('name'=>'Custom/Projects/Planner/EmployeeEvent','version'=>0),
			array('name'=>'Custom/Projects/Planner/OverviewEvent','version'=>0),
			array('name'=>'Custom/Projects/Planner/ProjectEvent','version'=>0),
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
