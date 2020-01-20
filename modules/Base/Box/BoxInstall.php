<?php
/**
 * BoxInit class.
 *
 * This class provides initialization of Box module.
 * 
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage box
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_BoxInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme(Base_BoxInstall::module_name());

		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(Base_BoxInstall::module_name());

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
			array('name'=>Base_Theme_AdministratorInstall::module_name(), 'version'=>0)
		);
	}

	public function simple_setup() {
		return __('EPESI Core');
	}
}
?>
