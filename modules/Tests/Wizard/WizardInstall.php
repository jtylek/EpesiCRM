<?php
/**
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage wizard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_WizardInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	public function version() {
		return array('2.0');
	}
	public function requires($v) {
		return array(array('name'=>Utils_CatFileInstall::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Utils_WizardInstall::module_name(),'version'=>0)
		);
	}
}

?>
