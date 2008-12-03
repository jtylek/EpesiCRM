<?php
/**
 * @author jtylek@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage gtalk
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_GTalkCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Google Talk";
	}

	public static function applet_info() {
		return "Embeded GTalk applet";
	}

}

?>