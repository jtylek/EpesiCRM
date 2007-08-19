<?php
/**
 * MaintenanceModeInstall class.
 * 
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage maintenance-mode
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MaintenanceModeInstall extends ModuleInstall {
	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
	
	public static function version() {
		return array('1.0.0');
	}
	public static function requires_0() {
		return array();
	}
}

?>
