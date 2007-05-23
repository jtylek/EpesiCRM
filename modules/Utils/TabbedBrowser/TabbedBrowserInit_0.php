<?php
/**
 * TabbedBrowserInit_0 class.
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
class Utils_TabbedBrowserInit_0 extends ModuleInit {

	public static function requires() {
		return array(array('name'=>'Base/Theme','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
