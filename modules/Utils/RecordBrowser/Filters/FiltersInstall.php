<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2016, Georgi Hristov
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser-Filters
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_FiltersInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>Libs_QuickFormInstall::module_name(), 'version'=>0),
		); 
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'<a href="mailto:ghristov@gmx.de">Georgi Hristov</a>',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return __('EPESI Core');
	}
	
}

?>