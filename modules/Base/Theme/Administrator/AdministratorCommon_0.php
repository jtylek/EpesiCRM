<?php
/**
 * Theme_AdministratorInit_0 class.
 * 
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage theme-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Theme_AdministratorCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return array('label'=>__('Change theme'), 'section'=>__('Server Configuration'));
	}	

	public static function body_access() {
		return Base_AclCommon::i_am_admin();
	}
	
}

?>
