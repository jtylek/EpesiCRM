<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license EPL
 * @package epesi-base-extra
 * @subpackage mail-contactus
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Mail_ContactUsCommon extends ModuleCommon {
	public static function menu() {
		return array('Help'=>array('__submenu__'=>1,'__weight__'=>1000,'Support'=>array()));
	}
}
?>
