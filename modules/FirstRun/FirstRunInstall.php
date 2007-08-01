<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-firstrun
 * @subpackage first-run
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class FirstRunInstall extends ModuleInstall {

	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
	
	public static function version() {
		return array("1.0");
	}
	
}

?>