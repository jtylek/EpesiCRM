<?php

/**
 *
 * @author msteczkiewicz@telaxus.com
 * @copyright msteczkiewicz@telaxus.com
 * @license EPL
 * @version 1.2
 * @package applets-google
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_GoogleCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Google";
	}

	public static function applet_info() {
		return "Simple Google Search applet";
	}
}

?>
