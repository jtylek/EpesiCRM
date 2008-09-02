<?php
/**
 * 
 * @author abisaga@telaxus.com
 * @copyright abisaga@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-monthview
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
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'CRM/Calendar','version'=>0),
			array('name'=>'Base/Lang','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'abisaga@telaxus.com',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>