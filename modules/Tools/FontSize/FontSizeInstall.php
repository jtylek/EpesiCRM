<?php
/**
 * Tools_FontSize class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @license SPL
 * @package tools-fontsize
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
}

?>
