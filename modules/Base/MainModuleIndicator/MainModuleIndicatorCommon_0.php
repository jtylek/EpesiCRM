<?php
/**
 * Theme_AdministratorInit_0 class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package epesi-base-extra
 * @subpackage theme-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MainModuleIndicatorCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return "Module indicator settings";
	}	
}
?>