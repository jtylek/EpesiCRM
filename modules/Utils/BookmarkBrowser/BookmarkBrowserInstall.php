<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-utils
 * @subpackage bookmark-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_BookmarkBrowserInstall extends ModuleInstall {
	public static function install() {
		Base_ThemeCommon::install_default_theme('Utils/BookmarkBrowser');
		return true;
	}
	
	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/BookmarkBrowser');
		return true;
	}
	public static function requires_0() {
		return array(array('name'=>'Base/Theme','version'=>0));
	}
}

?>
