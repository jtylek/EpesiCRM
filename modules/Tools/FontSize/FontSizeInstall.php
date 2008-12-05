<?php
/**
 * Tools_FontSize class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tools
 * @subpackage fontsize
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_FontSizeInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('Tools/FontSize');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeComon::uninstall_default_theme('Tools/FontSize');
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(
			array('name'=>'Base/Theme','version'=>0)
		);
	}

	public static function info() {
		return array(
			'Description'=>'Change font size from menu.',
			'Author'=>'Kuba Slawinski (kslawinski@telaxus.com) and Paul Bukowski (pbukowski@telaxus.com)',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return true;
	}
}

?>
