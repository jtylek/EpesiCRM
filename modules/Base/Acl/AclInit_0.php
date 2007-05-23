<?php
/**
 * AclInit class.
 * 
 * This class provides initialization data for Acl module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Acl module.
 * @package tcms-base-extra
 * @subpackage acl
 */
class Base_AclInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Base/Lang', 'version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}
?>
