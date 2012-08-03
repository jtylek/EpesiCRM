<?php
/**
 * User_AdministratorInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage user-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_AdministratorInstall extends ModuleInstall {
	public function version() {
		return array('1.0.0');
	}
	
	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	public function requires($v) {
		return array(
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/Admin','version'=>0), 
			array('name'=>'Base/Theme','version'=>0), 
			array('name'=>'Base/Acl','version'=>0), 
			array('name'=>'Utils/GenericBrowser','version'=>0), 
			array('name'=>'Base/User','version'=>0), 
			array('name'=>'Base/ActionBar','version'=>0), 
			array('name'=>'Base/User/Login','version'=>0), 
			array('name'=>'Base/Lang','version'=>0));
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>
