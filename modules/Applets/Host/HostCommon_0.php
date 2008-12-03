<?php
/**
 * Gets host ip or domain
 * @author pbukowski@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage host
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_HostCommon extends ModuleCommon {
	public static function applet_caption() {
		return "IP utils";
	}

	public static function applet_info() {
		return "Gets host ip or domain"; //here can be associative array
	}
	
}

?>