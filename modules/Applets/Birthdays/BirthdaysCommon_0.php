<?php
/**
 * 
 * @author jtylek@telaxus.com
 * @copyright jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-birthdays
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_BirthdaysCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Birthdays";
	}

	public static function applet_info() {
		return "Displays upcoming Birthdays of your favorite contacts";
	}

}

?>