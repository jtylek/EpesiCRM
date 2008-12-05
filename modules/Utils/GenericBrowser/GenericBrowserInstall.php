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
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme('Utils/GenericBrowser');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/GenericBrowser');
		return true;
	}

	public function version() {
		return array('1.0');
	}	
	public function requires($v) {
		return array(
			array('name'=>'Base/Acl','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Utils/Tooltip','version'=>0), 
			array('name'=>'Base/MaintenanceMode','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0),
			array('name'=>'Base/Theme','version'=>0));
	}
}

?>