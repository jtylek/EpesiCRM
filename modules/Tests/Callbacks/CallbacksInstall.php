<?php
/**
 * Example event module
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tests
 * @subpackage callbacks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_CallbacksInstall extends ModuleInstall {

	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	public function version() {
		return array("1.0.0");
	}
	
	public function requires($v) {
		return array(array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Tests/Callbacks/a','version'=>0),
			array('name'=>'Utils/CatFile','version'=>0));
	}
}

?>