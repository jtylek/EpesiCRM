<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-firstrun
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class FirstRunInit_0 extends ModuleInit {

	public static function requires() {
		return array(
			array('name'=>'Utils/Wizard','version'=>0),
			array('name'=>'Base/Lang','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>