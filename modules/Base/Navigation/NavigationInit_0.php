<?php
/**
 * TestInit_0 class.
 * 
 * This class provides initialization data for Test module.
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
 * @subpackage navigation
 */
class Base_NavigationInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Base/Theme','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>

