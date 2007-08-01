<?php
/**
 * AclInit class.
 * 
 * This class provides initialization data for Acl module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0\
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage acl
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AclInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Base/Lang', 'version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}
?>
