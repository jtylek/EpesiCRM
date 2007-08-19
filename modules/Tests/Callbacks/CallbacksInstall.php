<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 * @subpackage callbacks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_CallbacksInstall extends ModuleInstall {

	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
	public static function version() {
		return array("1.0.0");
	}
	
	public static function requires_0() {
		return array(array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Utils/CatFile','version'=>0));
	}
}

?>