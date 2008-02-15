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

class CRM_ProjectPlanner_EmployeeEventCommon extends Utils_Calendar_EventCommon {
	public static $employee;
	public static $project;

	public static function get($id) {
		$result = array();
		return $result;
	}
	public static function get_all($start,$end,$order='') {
		return DB::GetAll('SELECT \'\' as additional_info,\'\' as additional_info2, \'\' as color,start,end-start as duration,project_id as title,\'\' as description,id,0 as timeless FROM crm_projectplanner_work WHERE ((start>=%d AND start<%d))',array($start,$end));
	}

	public static function delete($id) {

	}

	public static function update($id,$start,$duration,$timeless) {
	}
}

?>
