<?php
/**
 * Mail_ContactUsInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage mail-contactus
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Mail_ContactUsInstall extends ModuleInstall {
	public function install() {
	    return true;
	}
	
	public function uninstall() {
	    return true;
	}

	public function requires($v) {
		return array(
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/Mail', 'version'=>0), 
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/StatusBar', 'version'=>0),
			array('name'=>'Base/User/Login', 'version'=>0));
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>
