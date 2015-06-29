<?php
/**
 * BoxInit class.
 *
 * This class provides initialization of Box module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage box
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_BoxInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme(Base_Box::module_name());

		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(Base_Box::module_name());

		return true;
	}

	public function version() {
		return array('1.0.0');
	}

	public function requires($v) {
		return array (
			array('name'=>Base_LangInstall::module_name(), 'version'=>0),
			array('name'=>Base_Setup::module_name(), 'version'=>0),
			array('name'=>Utils_TooltipCommon::module_name(), 'version'=>0),
			array('name'=>Base_Acl::module_name(), 'version'=>0),
			array('name'=>Base_Theme_Administrator::module_name(), 'version'=>0)
		);
	}

	public function simple_setup() {
		return __('EPESI Core');
	}
}
?>
