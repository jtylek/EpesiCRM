<?php
/**
 * Help class.
 *
 * This class provides interactive help.
 *
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2012, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage help
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_HelpInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme(Base_HelpInstall::module_name());
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(Base_HelpInstall::module_name());
		return true;
	}

	public function version() {
		return array('1.0.0');
	}

	public function requires($v) {
		return array (
			array('name'=>Base_LangInstall::module_name(), 'version'=>0),
			array('name'=>Base_SetupInstall::module_name(), 'version'=>0),
			array('name'=>Utils_TooltipInstall::module_name(), 'version'=>0),
			array('name'=>Base_AclInstall::module_name(), 'version'=>0),
			array('name'=>Base_BoxInstall::module_name(), 'version'=>0),
			array('name'=>Base_Theme_AdministratorInstall::module_name(), 'version'=>0)
		);
	}

	public function simple_setup() {
		return __('EPESI Core');
	}
}
?>
