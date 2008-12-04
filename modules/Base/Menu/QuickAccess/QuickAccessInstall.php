<?php
/**
 * QuickAccessInstall class.
 * 
 * This class provides initialization data for QuickAccess module.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage menu-quickaccess
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Menu_QuickAccessInstall extends ModuleInstall {
	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(array('name'=>'Base/Lang','version'=>0),
				array('name'=>'Libs/QuickForm','version'=>0), 
				array('name'=>'Base/Menu','version'=>0),  
				array('name'=>'Base/Theme','version'=>0),  
				array('name'=>'Base/Acl','version'=>0));
	}
}

?>
