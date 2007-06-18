<?php
/**
 * DirtyReadInit_0 class.
 * 
 * This class provides initialization data for DirtyRead module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for DirtyRead module.
 * @package epesi-utils
 * @subpackage dirty-read
 */
class Utils_DirtyReadInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Base/Lang','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
