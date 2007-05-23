<?php
/**
 * Mail_ContactUsInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package tcms-base-extra
 * @subpackage mail-contactus
 */
class Base_Mail_ContactUsInstall extends ModuleInstall {
	public static function install() {
	    return true;
	}
	
	public static function uninstall() {
	    return true;
	}
}

?>
