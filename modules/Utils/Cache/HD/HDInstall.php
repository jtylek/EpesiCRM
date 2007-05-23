<?php
/**
 * CacheInstall class.
 * 
 * This class provides initialization data for Cache module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Cache module.
 * @package tcms-utils
 * @subpackage Cache
 */
class Utils_Cache_HDInstall extends ModuleInstall {
	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
}

?>
