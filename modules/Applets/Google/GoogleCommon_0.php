<?php
/**
 * @author msteczkiewicz@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.2
 * @package epesi-applets
 * @subpackage google
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_GoogleCommon extends ModuleCommon {
	public static function applet_caption() {
		return __('Google');
	}

	public static function applet_info() {
		return __('Simple Google Search applet');
	}
}

?>
