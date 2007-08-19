<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 * @subpackage bookmark-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_BookmarkBrowserInstall extends ModuleInstall {

	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
	public static function version() {
		return array("0.1");
	}
	
	public static function requires_0() {
		return array(
			array('name'=>'Utils/CatFile','version'=>0),
			array('name'=>'Utils/BookmarkBrowser','version'=>0));
	}
}

?>