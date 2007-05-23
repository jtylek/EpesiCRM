<?php
/**
 * SearchInit_0 class.
 * 
 * This class provides initialization data for Search module.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Search module.
 * @package tcms-base-extra
 * @subpackage search
 */

class Base_SearchInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Box','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
