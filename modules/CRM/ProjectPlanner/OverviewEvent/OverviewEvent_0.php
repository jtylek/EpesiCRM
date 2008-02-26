<?php
/**
 * Calendar event module
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ProjectPlanner_OverviewEvent extends Utils_Calendar_Event {
	private $lang;

	public function view($id) {
		$this->go_to_project(DB::GetOne('SELECT project_id FROM crm_projectplanner_work WHERE id=%d',array($id)));
	}

	public function edit($id) {
		$this->go_to_project(DB::GetOne('SELECT project_id FROM crm_projectplanner_work WHERE id=%d',array($id)));
	}

	public function add($def_date,$timeless=false) {
		$this->go_to_project(substr($timeless,1));
	}

	private function go_to_project($id) {
		location(array('crm_projectplanner_project'=>$id));
		$this->back_to_calendar();

	}

}

?>
