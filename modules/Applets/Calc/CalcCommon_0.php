<?php

/**
 *
 * @author msteczkiewicz@telaxus.com
 * @copyright msteczkiewicz@telaxus.com
 * @license EPL
 * @version 0.9
 * @package applets-calc
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
