<?php
/**
 * DirtyReadInstall class.
 * 
 * This class provides initialization data for DirtyRead module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for DirtyRead module.
 * @package tcms-utils
 * @subpackage dirty-read
 */
class Utils_DirtyReadInstall extends ModuleInstall {
	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
}

?>
