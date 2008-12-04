<?php
/**
 * About Epesi
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage about
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AboutInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Utils/Tooltip','version'=>0),
			array('name'=>'Libs/Leightbox','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'About Epesi',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>