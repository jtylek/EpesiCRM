<?php
/**
 * TabbedBrowserInstall class.
 * 
 * This class provides initialization data for TabbedBrowser module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage tabbed-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TabbedBrowserInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('Utils/TabbedBrowser');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/TabbedBrowser');
		return true;
	}
	
	public function version() {
		return array('1.0');
	}
	public function requires($v) {
		return array(array('name'=>'Base/Theme','version'=>0));
	}
}

?>
