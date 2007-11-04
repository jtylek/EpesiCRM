<?php
/**
 * Lang_AdministratorInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage lang-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Lang_AdministratorInstall extends ModuleInstall {
	public function version() {
		return array('1.0.0');
	}
	
	public function install() {
		return Variable::set('allow_lang_change',true);
		Base_ThemeCommon::install_default_theme($this->get_type());
	}
	
	public function uninstall() {
		return Variable::delete('allow_lang_change');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
	}
	public function requires($v) {
		return array(
			array('name'=>'Base/Admin','version'=>0), 
			array('name'=>'Base/Acl','version'=>0), 
			array('name'=>'Base/Theme','version'=>0), 
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/User','version'=>0), 
			array('name'=>'Utils/GenericBrowser','version'=>0), 
			array('name'=>'Base/User/Settings','version'=>0), // TODO: not required directly but needed to make this module fully operational. Should we delete the requirement? 
			array('name'=>'Base/Lang','version'=>0));
	}
}

?>
