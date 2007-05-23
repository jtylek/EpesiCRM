<?php
/**
 * Error class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package tcms-base-extra
 * @subpackage error
 */
class Base_ErrorInit_0 extends ModuleInit {
	public static function requires() {
		return array(
            array('name'=>'Base/Mail', 'version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
