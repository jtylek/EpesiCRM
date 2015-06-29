<?php
/**
 * MainModuleIndicatorInstall class.
 * 
 * This class provides initialization data for MainModuleIndicator module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage MainModuleIndicator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MainModuleIndicatorInstall extends ModuleInstall {
	public function install() {
		Variable::set('base_page_title','EPESI');
		Variable::set('show_caption_in_title','1');
		Variable::set('show_module_indicator','1');
		Variable::set('logo_file','');
        Variable::set('login_logo_file','');
		Base_ThemeCommon::install_default_theme(Base_MainModuleIndicator::module_name());
		$this->create_data_dir();
		return true;
	}
	
	public function uninstall() {
		Variable::delete('logo_file');
        Variable::delete('login_logo_file');
		Variable::delete('base_page_title');
		Variable::delete('show_caption_in_title');
		Variable::delete('show_module_indicator');
		Base_ThemeCommon::uninstall_default_theme(Base_MainModuleIndicator::module_name());
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(
			array('name'=>Base_Box::module_name(), 'version'=>0),
			array('name'=>Base_Admin::module_name(), 'version'=>0),
			array('name'=>Base_LangInstall::module_name(), 'version'=>0),
			array('name'=>Libs_QuickForm::module_name(), 'version'=>0),
			array('name'=>Base_Theme::module_name(), 'version'=>0));
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>
