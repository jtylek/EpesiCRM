<?php
/**
 * BaseInstall class.
 * 
 * This class initialization data for Base pack of module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package tcms-base-extra
 * @subpackage base-installer
 */
class BaseInstall extends ModuleInstall {
	public static function install() {
		if(!Base_UserCommon::add_user('admin')) {
		    print('Unable to create user');
		    return false;
		}
		
		$user_id = Base_UserCommon::get_user_id('admin');
		if($user_id===false) {
		    print('Unable to get admin user id');
		    return false;
		}
		if(!DB::Execute('INSERT INTO user_password VALUES(%d,%s, %s)', array($user_id, md5('admin'), 'admin@example.com'))) {
		    print('Unable to set user password');
		    return false;
		}
		
		if(!Base_AclCommon::change_privileges('admin', array(Base_AclCommon::sa_group_id()))) {
			print('Unable to update admin account data (groups).');
			return false;
		}
		
		if(!Variable::set('anonymous_setup','0')) return false;
		if(!Variable::set('default_module','Base_Box')) return false;
			
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
}

?>
