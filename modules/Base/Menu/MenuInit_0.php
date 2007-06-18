<?php
/**
 * MenuInit_0 class.
 * 
 * This class provides initialization data for Menu module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Menu module.
 * @package epesi-base-extra
 * @subpackage menu
 */
class Base_MenuInit_0 extends ModuleInit {
	public static function requires() {
		return array(
//			array('name'=>'Base/Menu/QuickAccess','version'=>0), 
			array('name'=>'Base/Box','version'=>0), 
			array('name'=>'Base/Lang','version'=>0), 
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Utils/Menu','version'=>0)
		);
	}
	
	public static function provides() {
		return array();
	}
}

?>
