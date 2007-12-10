<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class Utils_Calendar_EventCommon extends ModuleCommon {
	abstract static function get($start,$end);
	abstract static function delete($id);
}
?>