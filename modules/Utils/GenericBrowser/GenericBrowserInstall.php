<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>, Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage generic-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GenericBrowserInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme(Utils_GenericBrowser::module_name());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(Utils_GenericBrowser::module_name());
		return true;
	}

	public function version() {
		return array('1.0');
	}	
	public function requires($v) {
		return array(
			array('name'=>Base_Acl::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Utils_TooltipCommon::module_name(),'version'=>0),
			array('name'=>Base_User_Settings::module_name(),'version'=>0),
			array('name'=>Base_Theme::module_name(),'version'=>0));
	}
}

?>