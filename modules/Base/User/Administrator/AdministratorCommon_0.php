<?php
/**
 * User_Administrator class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage user-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');


class Base_User_AdministratorCommon extends Base_AdminModuleCommon {
	public static function user_settings() {
		if(Base_AclCommon::i_am_user()) return array(__('Account')=>'body');
		return array();
	}
	
	public static function admin_caption() {
		return array('label'=>__('Manage users'), 'section'=>__('User Management'));
	}
	
	public static function menu() {
		if (!Base_AclCommon::check_permission('Advanced User Settings'))
			return array(_M('My settings')=>array('__weight__'=>10,'__submenu__'=>1,_M('Change password')=>array()));
	}
}
?>