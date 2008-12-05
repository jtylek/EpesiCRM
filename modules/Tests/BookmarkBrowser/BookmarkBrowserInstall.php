<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage bookmark-browser
 */
 
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_BookmarkBrowserInstall extends ModuleInstall {

	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Utils/CatFile','version'=>0),
			array('name'=>'Utils/BookmarkBrowser','version'=>0));
	}
}

?>