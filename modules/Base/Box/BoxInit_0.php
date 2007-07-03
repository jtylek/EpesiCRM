<?php
/**
 * BoxInit class.
 * 
 * This class provides initialization of Box module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization of Box module.
 * @package epesi-base-extra
 * @subpackage box
 */
class Base_BoxInit_0 extends ModuleInit {
	public static function requires() {
		return array (
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/Setup', 'version'=>0),
			array('name'=>'Base/Acl', 'version'=>0),
			array('name'=>'Base/Theme/Administrator', 'version'=>0)
		);
	}
	
	public static function provides() {
		return array();
	}
}
?>
