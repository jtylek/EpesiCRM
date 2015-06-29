<?php
/**
 * WizardInstall class.
 * 
 * This class provides initialization data for Wizard module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com> and Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage wizard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_WizardInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme(Utils_Wizard::module_name());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(Utils_Wizard::module_name());
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Base_Theme::module_name(),'version'=>0),
			array('name'=>Libs_QuickForm::module_name(),'version'=>0)
		);
	}
}

?>
