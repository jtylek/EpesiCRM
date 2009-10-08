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

class Tests_Calendar_EventCommon extends Utils_Calendar_EventCommon {
	public static function get_event_days($start,$end) {
		return array();
	}

	public static function get_all($start,$end) {
		return DB::GetAll('SELECT \'\' as additional_info,\'\' as additional_info2, color,start,duration,title,description,id,timeless FROM tests_calendar_event WHERE ((start>=%d AND start<%d))',array(strtotime($start),strtotime($end)));
	}

	public static function get($id) {
		return DB::GetRow('SELECT \'\' as additional_info, \'\' as additional_info2, color,start,duration,title,description,id,timeless FROM tests_calendar_event WHERE id=%d',array($id));
	}

	public static function delete($id) { //make sure that event owner is Acl::get_user....
		DB::Execute('DELETE FROM tests_calendar_event WHERE id=%d',array($id));
		return true;
	}

	public static function update(&$id,$start,$duration,$timeless) { //make sure that event owner is Acl::get_user....
		DB::Execute('UPDATE tests_calendar_event SET duration=%d, start=%d, timeless=%b WHERE id=%d',array($duration,$start,$timeless,$id));
		return true;
	}
}

?>
