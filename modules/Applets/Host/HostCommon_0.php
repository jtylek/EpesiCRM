<?php
/**
 * Gets host ip or domain
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-host
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