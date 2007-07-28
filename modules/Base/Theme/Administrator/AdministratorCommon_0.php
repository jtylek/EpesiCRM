<?php
/**
 * Theme_AdministratorInit_0 class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package epesi-base-extra
 * @subpackage theme-administrator
 */
class Base_Theme_AdministratorCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return "Change theme";
	}	

	public static function body_access() {
		return Base_AclCommon::i_am_admin();
	}
}
?>