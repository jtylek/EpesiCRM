<?php
/**
 * AdminInit class.
 * 
 * This class provides initialization data for administration module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Admin module.
 * @package tcms-base-extra
 * @subpackage admin
 */
class Base_AdminInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Acl', 'version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
