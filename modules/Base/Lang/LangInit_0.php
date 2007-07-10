<?php
/**
 * LangInit_0 class.
 * 
 * This class provides initialization data for Lang module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Lang module.
 * @package epesi-base-extra
 * @subpackage lang
 */
class Base_LangInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Libs/QuickForm','version'=>0),
				array('name'=>'Base/MaintenanceMode','version'=>0),
				array('name'=>'Base/Acl','version'=>0));
	}
	
	public static function provides() {
		return array();
	}	
}
?>
