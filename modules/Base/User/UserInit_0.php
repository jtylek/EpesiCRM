<?php
/**
 * UserInit_0 class.
 * 
 * This class provides initialization data for User module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for User module.
 * @package tcms-base-extra
 * @subpackage user
 */
class Base_UserInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Base/Acl','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

	public static function backup($v) {
		return array('user_login');
	}
}
?>
