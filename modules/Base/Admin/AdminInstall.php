<?php
/**
 * Admin class.
 * 
 * This class provides administration module.
 * 
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage admin
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AdminInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme(Base_AdminInstall::module_name());
		DB::CreateTable('base_admin_access',
			'id I4 AUTO KEY,'.
			'module C(128),'.
			'section C(64),'.
			'allow I1',
			array('constraints'=>''));
		return true;
	}
	
	public function uninstall() {
		DB::DropTable('base_admin_access');
		Base_ThemeCommon::uninstall_default_theme(Base_AdminInstall::module_name());
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}

	public function requires($v) {
		return array(
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Base_AclInstall::module_name(), 'version'=>0));
	}
}

?>
