<?php
/**
 * MainModuleIndicatorInstall class.
 * 
 * This class provides initialization data for MainModuleIndicator module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage MainModuleIndicator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MainModuleIndicatorInstall extends ModuleInstall {
	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Variable::set('base_page_title','Epesi');
		Variable::set('show_caption_in_title','1');
		Variable::set('show_module_indicator','1');
		Base_ThemeCommon::install_default_theme('Base/MainModuleIndicator');
		return true;
	}
	
	public function uninstall() {
		Variable::delete('base_page_title');
		Variable::delete('show_caption_in_title');
		Variable::delete('show_module_indicator');
		Base_ThemeCommon::uninstall_default_theme('Base/MainModuleIndicator');
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(
			array('name'=>'Base/Box', 'version'=>0),
			array('name'=>'Base/Admin', 'version'=>0),
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Libs/QuickForm', 'version'=>0),
			array('name'=>'Base/Theme', 'version'=>0));
	}
}

?>
