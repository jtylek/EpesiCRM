<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-libs
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_LeightboxInit_0 extends ModuleInit {

	public static function requires() {
		return array(array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Libs/ScriptAculoUs','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>