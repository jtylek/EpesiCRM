<?php
/**
 * Calendar event module
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package custom-projects-planner-overviewevent
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Custom_Projects_Planner_OverviewEvent extends Utils_Calendar_Event {
	private $lang;

	public function view($id) {
		$this->go_to_project(DB::GetOne('SELECT project_id FROM custom_projects_planner_work WHERE id=%d',array($id)));
	}

	public function edit($id) {
		$this->go_to_project(DB::GetOne('SELECT project_id FROM custom_projects_planner_work WHERE id=%d',array($id)));
	}

	public function add($def_date,$timeless=false) {
		$this->go_to_project(substr($timeless,1));
	}

	private function go_to_project($id) {
		location(array('custom_projects_planner_project'=>$id));
		$this->back_to_calendar();

	}

}

?>
