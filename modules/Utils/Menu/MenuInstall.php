<?php
/** 
 * @author Janusz Tylek <j@epe.si> 
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek 
 * @version 1.9.0
 * @license MIT 
 * @package epesi-utils 
 * @subpackage menu
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_MenuInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme(Utils_MenuInstall::module_name());
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array();
	}
}

?>
