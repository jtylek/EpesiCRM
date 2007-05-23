<?php
/**
 * MainModuleIndicatorInit_0 class.
 * 
 * This class provides initialization data for MainModuleIndicator module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for MainModuleIndicator module.
 * @package tcms-base-extra
 * @subpackage MainModuleIndicator
 */
class Base_MainModuleIndicatorInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Base/Box', 'version'=>0),
			array('name'=>'Base/Theme', 'version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}
?>
