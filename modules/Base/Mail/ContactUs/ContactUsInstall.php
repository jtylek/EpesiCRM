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
			array('name'=>Base_MenuInstall::module_name(),'version'=>0),
		);
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}

	public function version() {
		return array('1.0');
	}
}

?>
