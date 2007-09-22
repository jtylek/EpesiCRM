<?php
/**
 * Flash clock
 * (clock taken from http://www.kirupa.com/developer/actionscript/clock.htm)
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-clock
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_ClockCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Clock";
	}

	public static function applet_info() {
		return "Analog flash clock"; //here can be associative array
	}
}

?>