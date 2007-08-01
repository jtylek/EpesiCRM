<?php
/**
 * Admin class.
 * 
 * This class provides administration module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage admin
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

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
