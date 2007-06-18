<?php
/**
 * HomePageInit class.
 * 
 * This class provides initialization data for HomePage module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for HomePage module.
 * @package epesi-base-extra
 * @subpackage homepage
 */
class Base_HomePageInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Base/Box','version'=>0), 
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/User', 'version'=>0),
			array('name'=>'Base/ActionBar', 'version'=>0)
			);
	}
	
	public static function provides() {
		return array();
	}
}

?>
