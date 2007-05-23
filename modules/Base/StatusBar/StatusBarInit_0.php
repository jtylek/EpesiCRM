<?php
/**
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package tcms-base-extra
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
