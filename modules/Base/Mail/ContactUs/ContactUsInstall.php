<?php
/**
 * Mail_ContactUsInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package epesi-base-extra
 * @subpackage mail-contactus
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Mail_ContactUsInstall extends ModuleInstall {
	public static function install() {
	    return true;
	}
	
	public static function uninstall() {
	    return true;
	}
}

?>
