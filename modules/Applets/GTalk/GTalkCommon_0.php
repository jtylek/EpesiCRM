<?php
/**
 * 
 * @author jtylek@telaxus.com
 * @copyright jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-gtalk
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