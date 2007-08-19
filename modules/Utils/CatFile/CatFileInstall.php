<?php
/**
 * CatFileInstall class.
 * 
 * This class provides initialization data for CatFile module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-utils
 * @subpackage catfile
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CatFileInstall extends ModuleInstall {
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
