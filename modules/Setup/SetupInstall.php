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
class SetupInstall extends ModuleInstall {
	public static function version() {
		return 0;
	}
	
	public static function install() {		
		$ret = Variable::set('anonymous_setup',true);
		if($ret === false) {
			print('Invalid SQL query - Setup module (populating variables)');
			return false;
		}
		$ret = Variable::set('simple_setup',true);
		if($ret === false) {
			print('Invalid SQL query - Setup module (populating variables)');
			return false;
		}

		return true;
	}

	public static function uninstall() {
		return Variable::delete('anonymous_setup');
		return Variable::delete('simple_setup');
	}
}
?>
