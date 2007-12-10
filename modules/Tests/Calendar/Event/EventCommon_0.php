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

class Tests_Calendar_EventCommon extends Utils_Calendar_EventCommon {
	public static function get($start,$end) {
		return DB::GetAll('SELECT start,duration,title,description,id,timeless FROM tests_calendar_event WHERE (start+duration>=%d AND start<=%d) OR (start+duration>=%d AND start<=%d)',array($start,$start,$end,$end));
	}

	public static function delete($id) {
		DB::Execute('DELETE FROM tests_calendar_event WHERE id=%d',array($id));
	}
}

?>