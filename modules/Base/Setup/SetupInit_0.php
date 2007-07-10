<?php
/**
 * Setup initial class
 * 
 * This file contains default database and setup module initialization data.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class initializes setup module.
 * @package epesi-base
 * @subpackage setup
 */
class Base_SetupInit_0 extends ModuleInit {
	public static function requires() {
		return array (
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Libs/Leightbox','version'=>0),
			array('name'=>'Utils/Tree','version'=>0)
		);
	}
	
	public static function provides() {
		return array();
	}
}
?>
