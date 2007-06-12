<?php
/**
 * TabbedBrowserInstall class.
 * 
 * This class provides initialization data for TabbedBrowser module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for TabbedBrowser module.
 * @package tcms-utils
 * @subpackage tabbed-browser
 */
class Utils_BookmarkBrowserInstall extends ModuleInstall {
	public static function install() {
		Base_ThemeCommon::install_default_theme('Utils/BookmarkBrowser');
		return true;
	}
	
	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/BookmarkBrowser');
		return true;
	}
}

?>
