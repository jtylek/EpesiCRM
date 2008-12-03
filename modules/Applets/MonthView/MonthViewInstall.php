<?php
/**
 * @author abisaga@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage monthview
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_MonthViewInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		Base_LangCommon::install_translations($this->get_type());
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
			array('name'=>'CRM/Calendar','version'=>0),
			array('name'=>'Base/Lang','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Applet showing monthly calendar',
			'Author'=>'abisaga@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>