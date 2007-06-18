<?php
/**
 * Fancy statusbar.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package epesi-base-extra
 * @subpackage statusbar
 */
class Base_StatusBarInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Libs/ScriptAculoUs','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
