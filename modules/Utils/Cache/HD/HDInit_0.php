<?php
/**
 * CacheInit_0 class.
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
class Utils_Cache_HDInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Utils/Cache/Base','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
