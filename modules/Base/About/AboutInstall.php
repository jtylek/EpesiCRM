<?php
/**
 * About Epesi
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage about
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AboutInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
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
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Utils_TooltipInstall::module_name(),'version'=>0),
			array('name'=>Libs_LeightboxInstall::module_name(),'version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'About Epesi',
			'Author'=>'j@epe.si',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return __('EPESI Core');
	}
	
}

?>