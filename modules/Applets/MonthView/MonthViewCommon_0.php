<?php
/**
 * 
 * @author abisaga@telaxus.com
 * @copyright abisaga@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-monthview
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