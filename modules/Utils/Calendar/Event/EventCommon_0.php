<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Calendar_EventCommon extends ModuleCommon {
	public static function get_event_days($start,$end) {} /* must be in order */
	public static function get_all($start,$end) {}
	public static function get($id){}
	public static function delete($id){}
	public static function update(&$id,$start_time,$duration,$timeless){}
}
?>