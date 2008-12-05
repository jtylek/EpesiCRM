<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage bookmark-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_BookmarkBrowserInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('Utils/BookmarkBrowser');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/BookmarkBrowser');
		return true;
	}
	public function requires($v) {
		return array(array('name'=>'Base/Theme','version'=>0));
	}
}

?>
