<?php
/**
 * LoginInit_0 class.
 * 
 * This class provides initialization data for Login module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Login module.
 * @package tcms-base-extra
 * @subpackage user-login
 */
class Base_User_LoginInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/User','version'=>0), 
			array('name'=>'Base/Theme','version'=>0), 
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/Mail', 'version'=>0));
	}
	
	public static function provides() {
		return array();
	}
	
	public static function backup($v) {
		return array('user_password');
	}
}

?>
