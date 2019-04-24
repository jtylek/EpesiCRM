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
		return Acl::i_am_user() ? [
				__('Account') => 'body'
		]: [];
	}
	
	public static function admin_caption() {
		return [
				'label' => __('Manage users'),
				'section' => __('User Management')
		];
	}
    
    public static function admin_access() {
        return !DEMO_MODE;
    }

    public static function admin_access_levels() {
		return [
				'log_as_user' => [
						'label' => __('Allow admin to login as user'),
						'default' => 1
				],
				'log_as_admin' => [
						'label' => __('Allow admin to login as other admin'),
						'default' => 0
				],
				'manage_ban' => [
						'label' => __('Allow admin to manage ban options and autologin'),
						'default' => 0
				]
		];
	}
	
	public static function menu() {
		if (! Acl::check_permission('Advanced User Settings')) return [
				_M('My settings') => [
						'__weight__' => 10,
						'__submenu__' => 1,
						_M('Change password') => []
				]
		];
	}
	
	public static function get_admin_access($level) {
		if (!Acl::i_am_admin()) return false;		
		
		if (Acl::i_am_sa()) return true;
		
		if (!in_array($level, array_keys(self::admin_access_levels()))) return false;		
		
		return Base_AdminCommon::get_access(Base_User_Administrator::class, $level);
	}
	
	public static function get_log_as_user_access($user) {
		static $admin_levels = false;
		static $my_level = false;
		
		if (!Acl::i_am_admin()) return false;
		
		if (Acl::i_am_sa()) return true;
		
		if ($admin_levels === false)
			$admin_levels = DB::GetAssoc('SELECT id, admin FROM user_login');
		if ($my_level === false)
			$my_level = $admin_levels[Acl::get_user()]?? 0;
		
		$user_level = $admin_levels[$user]?? 0;
			
		return $user_level == 0 && self::get_admin_access('log_as_user') ||  // contact is user and I can login as user
				$user_level == 1 && self::get_admin_access('log_as_admin');
	}
}
?>