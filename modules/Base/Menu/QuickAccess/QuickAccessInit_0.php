<?php
/**
 * QuickAccessInit_0 class.
 * 
 * This class provides initialization data for QuickAccess module.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage menu-quick-access
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Menu_QuickAccessInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Base/Lang','version'=>0),
				array('name'=>'Libs/QuickForm','version'=>0), 
				array('name'=>'Base/Menu','version'=>0),  
				array('name'=>'Base/Acl','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
