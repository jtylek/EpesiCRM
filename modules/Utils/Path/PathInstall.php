<?php
/** 
 * @author Kuba Slawinski <kslawinski@telaxus.com> 
 * @copyright Copyright &copy; 2006, Telaxus LLC 
 * @version 0.9 
 * @license SPL
 * @package epesi-utils 
 * @subpackage path
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_PathInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('Utils/Path');
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array('0.9.0');
	}
	public function requires($v) {
		return array();
	}
}

?>
