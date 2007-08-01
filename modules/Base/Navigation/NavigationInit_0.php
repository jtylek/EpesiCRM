<?php
/**
 * Navigation component: back, refresh, forward.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage navigation
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_NavigationInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Base/Theme','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>

