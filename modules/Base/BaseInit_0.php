<?php
/**
 * BaseInit_0 class.
 * 
 * This class initialization data for Base pack of module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package epesi-base-extra
 * @subpackage base-installer
 */
class BaseInit_0 extends ModuleInit {
	public static function requires() {
		return array(
		    array('name'=>'Base/Admin','version'=>0),
		    array('name'=>'Base/ActionBar','version'=>0),
		    array('name'=>'Base/Backup','version'=>0),
		    array('name'=>'Base/Setup','version'=>0),
		    array('name'=>'Base/Lang/Administrator','version'=>0),
		    array('name'=>'Base/Mail/ContactUs','version'=>0),
		    array('name'=>'Base/MaintenanceMode/Administrator','version'=>0),
		    array('name'=>'Base/Menu/QuickAccess','version'=>0),
		    array('name'=>'Base/MainModuleIndicator','version'=>0),
		    array('name'=>'Base/Menu','version'=>0),
		    array('name'=>'Base/StatusBar','version'=>0),
		    array('name'=>'Base/Search','version'=>0),
		    array('name'=>'Base/HomePage','version'=>0),
		    array('name'=>'Base/Theme/Administrator','version'=>0),
		    array('name'=>'Base/User/Administrator','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
