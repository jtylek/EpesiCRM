<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-data
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Data_USAStatesInit_0 extends ModuleInit {

	public static function requires() {
		return array(
			array('name'=>'Base/Admin','version'=>0),
			array('name'=>'Utils/CommonData','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>