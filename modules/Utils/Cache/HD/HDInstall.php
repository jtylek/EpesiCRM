<?php
/**
 * CacheInstall class.
 * 
 * This class provides initialization data for Cache module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.4
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Cache module.
 * @package epesi-utils
 * @subpackage Cache
 */
class Utils_Cache_HDInstall extends ModuleInstall {
	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
	public static function requires_0() {
		return array(array('name'=>'Utils/Cache/Base','version'=>0));
	}
}

?>
