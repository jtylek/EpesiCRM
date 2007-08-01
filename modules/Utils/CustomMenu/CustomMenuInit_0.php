<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-utils
 * @subpackage custom-menu
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CustomMenuInit_0 extends ModuleInit {

	public static function requires() {
		return array(
			array('name'=>'Base/Lang','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>