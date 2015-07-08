<?php
/**
 * Theme_AdministratorInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage theme-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Theme_AdministratorInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		$this->create_data_dir();
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}

	public function version() {
		return array("1.0");
	}
	public function requires($v) {
		return array(
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Base_AdminInstall::module_name(),'version'=>0),
			array('name'=>Utils_FileUploadInstall::module_name(),'version'=>0),
			array('name'=>Utils_FileDownloadInstall::module_name(),'version'=>0),
			array('name'=>Utils_ImageInstall::module_name(),'version'=>0),
			array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0),
			array('name'=>Base_StatusBarInstall::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0));
		
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>
