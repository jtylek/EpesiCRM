<?php
/**
 * Mail_ContactUs class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage mail-contactus
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Mail_ContactUsCommon extends ModuleCommon {
	public static function menu() {
		return array(_M('Support')=>array('__submenu__'=>1,'__weight__'=>1000,_M('EPESI Forum')=>array('__url__'=>'http://forum.epesibim.com')));
	}
}
?>
