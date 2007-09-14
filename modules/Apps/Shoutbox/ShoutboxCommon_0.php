<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package apps-shoutbox
 * @license SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ShoutboxCommon extends ModuleCommon {
	public static function menu() {
		return array('Shoutbox'=>array());
	}
	
	public static function applet_caption() {
		return "Shoutbox";
	}

	public static function applet_info() {
		return "Mini shoutbox"; //here can be associative array
	}
}
?>
