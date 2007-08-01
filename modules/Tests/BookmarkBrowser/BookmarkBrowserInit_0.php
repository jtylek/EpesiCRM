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

class Tests_BookmarkBrowserInit_0 extends ModuleInit {

	public static function requires() {
		return array(
			array('name'=>'Utils/CatFile','version'=>0),
			array('name'=>'Utils/BookmarkBrowser','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>