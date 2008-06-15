<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class Utils_Calendar_EventCommon extends ModuleCommon {
	abstract static function get_event_days($start,$end); /* must be in order */
	abstract static function get_all($start,$end);
	abstract static function get($id);
	abstract static function delete($id);
	abstract static function update(&$id,$start_time,$duration,$timeless);
}
?>