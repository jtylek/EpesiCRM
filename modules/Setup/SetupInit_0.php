<?php
/**
 * Setup initial class
 * 
 * This file contains default database and setup module initialization data.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class initializes setup module.
 * @package tcms-base
 * @subpackage setup
 */
class SetupInit_0 extends ModuleInit {
	public static function requires() {
		return array (array('name'=>'Libs/QuickForm','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
	
	public static function backup() {
		return array();
	}
}
?>
