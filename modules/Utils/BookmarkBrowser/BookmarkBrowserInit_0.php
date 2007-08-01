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

class Utils_BookmarkBrowserInit_0 extends ModuleInit {

	public static function requires() {
		return array(array('name'=>'Base/Theme','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
