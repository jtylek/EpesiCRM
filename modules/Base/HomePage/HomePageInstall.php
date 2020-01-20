<?php
/**
 * HomePage class.
 *
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage homepage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_HomePageInstall extends ModuleInstall {
	public function install() {
        Base_ThemeCommon::install_default_theme($this->get_type());
		DB::CreateTable('base_home_page',
			'id I4 AUTO KEY,'.
			'priority I4,'.
			'home_page C(64)',
			array('constraints' => ''));
		DB::CreateTable('base_home_page_clearance',
			'id I4 AUTO KEY,'.
			'home_page_id I,'.
			'clearance C(64)',
			array('constraints' => ', FOREIGN KEY (home_page_id) REFERENCES base_home_page(id)'));
		return true;
	}
	
	public function uninstall() {
        Base_ThemeCommon::uninstall_default_theme($this->get_type());
		DB::DropTable('base_home_page');
		DB::DropTable('base_home_page_clearance');
		return true;
	}
	
	public function version() {
		return array('1.0');
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}

	public function requires($v) {
		return array(
			array('name'=>Base_BoxInstall::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(), 'version'=>0),
			array('name'=>Utils_ShortcutInstall::module_name(), 'version'=>0),
			array('name'=>Base_UserInstall::module_name(), 'version'=>0),
			array('name'=>Base_ActionBarInstall::module_name(), 'version'=>0)
			);
	}
	
}

?>
