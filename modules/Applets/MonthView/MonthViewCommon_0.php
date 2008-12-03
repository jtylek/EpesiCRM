<?php
/**
 * @author abisaga@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage monthview
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_MonthViewCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Month View";
	}

	public static function applet_info() {
		return "Displays Month and marks days with events";
	}

}

?>