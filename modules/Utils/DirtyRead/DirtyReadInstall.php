<?php
/**
 * DirtyReadInstall class.
 * 
 * This class provides initialization data for DirtyRead module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 * @subpackage dirty-read
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_DirtyReadInstall extends ModuleInstall {
	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}

	public static function version() {
		return array('0.9.6');
	}
}

?>
