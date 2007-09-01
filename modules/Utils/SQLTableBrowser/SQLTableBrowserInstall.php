<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.8
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_SQLTableBrowserInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme('Utils/SQLTableBrowser');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/SQLTableBrowser');
		return true;
	}
	
	public function version() {
		return array('0.8.0');
	}
	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0),
			array('name'=>'Utils/Tooltip','version'=>0));
	}
}

?>