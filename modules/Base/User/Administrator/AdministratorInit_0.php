<?php
/**
 * User_AdministratorInit_0 class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package epesi-base-extra
 * @subpackage user-administrator
 */

class Base_User_AdministratorInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/Admin','version'=>0), 
			array('name'=>'Base/Acl','version'=>0), 
			array('name'=>'Utils/GenericBrowser','version'=>0), 
			array('name'=>'Base/User','version'=>0), 
			array('name'=>'Base/ActionBar','version'=>0), 
			array('name'=>'Base/User/Login','version'=>0), 
			array('name'=>'Base/Lang','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
