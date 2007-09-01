<?php
/**
 * CacheInstall class.
 * 
 * This class provides initialization data for Cache module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.2
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Cache module.
 * @package epesi-utils
 * @subpackage Cache
 */
class Utils_Cache_BaseInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	public function requires($v) {
		return array();
	}
}

?>
