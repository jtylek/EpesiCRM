<?php
/**
 * @author msteczkiewicz@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage calc
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_CalcCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Calc";
	}

	public static function applet_info() {
		return "Simple calculator applet";
	}
}

?>
