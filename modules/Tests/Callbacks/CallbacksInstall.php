<?php
/**
 * Example event module
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
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
		return array(array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0),
			array('name'=>'Tests/Callbacks/a','version'=>0),
			array('name'=>Utils_CatFileInstall::module_name(),'version'=>0));
	}
}

?>