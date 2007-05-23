<?php
/**
 * Theme_AdministratorInit_0 class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Test module.
 * @package tcms-base-extra
 * @subpackage theme-administrator
 */

class Base_Theme_AdministratorInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/Admin','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/StatusBar','version'=>0),
			array('name'=>'Base/Lang','version'=>0));
		
	}
	
	public static function provides() {
		return array();
	}
}

?>
