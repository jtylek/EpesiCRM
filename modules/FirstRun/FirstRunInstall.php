<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-firstrun
 * @subpackage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class FirstRunInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme('FirstRun');
		Base_ThemeCommon::create_cache();
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('FirstRun');
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Utils_WizardInstall::module_name(),'version'=>0),
			array('name'=>Base_AclInstall::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0));
	}
	public static function simple_setup() {
		return false;
	}
}

?>