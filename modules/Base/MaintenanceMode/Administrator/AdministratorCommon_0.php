<?php
/**
 * MaintenanceMode_AdministratorComon class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage maintenancemode-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MaintenanceMode_AdministratorCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return "Maintenance mode";
	}
	
	public static function admin_access() {
		return Base_AclCommon::i_am_sa();
	}
}
?>
