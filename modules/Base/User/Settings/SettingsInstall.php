<?php
/**
 * User_Settings class.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage user-settings
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_SettingsInstall extends ModuleInstall {

	public function install() {
		global $database;
		$ret = true;
		$ret &= DB::CreateTable('base_user_settings','
			user_login_id I4 NOTNULL,
			module C(128) NOTNULL,
			variable C(64) NOTNULL,
			value X NOTNULL',
			array('constraints'=>', FOREIGN KEY (user_login_id) REFERENCES user_login(id), PRIMARY KEY(user_login_id,module,variable)'));
		if(!$ret){
			print('Unable to create table base_user_settings.<br>');
			return false;
		}
		$ret &= DB::CreateTable('base_user_settings_admin_defaults','
			module C(128) NOTNULL,
			variable C(64) NOTNULL,
			value X NOTNULL',
			array('constraints'=>', PRIMARY KEY(module,variable)'));
		if(!$ret){
			print('Unable to create table base_user_settings_defaults.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme('Base/User/Settings');
		Base_AclCommon::add_permission(_M('Advanced User Settings'),array('ACCESS:employee'));

		return $ret;
	}
	
	public function uninstall() {
		Base_AclCommon::delete_permission('Advanced User Settings');
		global $database;
		$ret = true;
		$ret &= DB::DropTable('base_user_settings');
		Base_ThemeCommon::uninstall_default_theme('Base/User/Settings');
		return $ret;
	}

	public function version() {
		return array("1.0");
	}

	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/User','version'=>0),
			array('name'=>'Base/User/Login','version'=>0));
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>