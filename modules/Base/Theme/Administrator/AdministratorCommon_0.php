<?php
/**
 * Theme_AdministratorInit_0 class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage theme-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Theme_AdministratorCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return "Change theme";
	}	

	public static function body_access() {
		return Base_AclCommon::i_am_admin();
	}
}
?>