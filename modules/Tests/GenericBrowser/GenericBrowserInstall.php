<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-tests
 * @subpackage generic-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_GenericBrowserInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function requires($v) {
		return array(array('name'=>Utils_CatFileInstall::module_name(),'version'=>0),
			array('name'=>Utils_GenericBrowserInstall::module_name(),'version'=>0));
	}
}

?>
