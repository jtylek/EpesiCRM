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

class Tests_BookmarkBrowserCommon {
	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'Bookmark Browser'=>array()));
	}
}

?>