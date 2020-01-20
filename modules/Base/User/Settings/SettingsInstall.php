<?php
/**
 * User_Settings class.
 * 
 * @author Arkadiusz Bisaga, Janusz Tylek and Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
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
		Base_ThemeCommon::install_default_theme(Base_User_SettingsInstall::module_name());
		Base_AclCommon::add_permission(_M('Advanced User Settings'),array('ACCESS:employee'));

		return $ret;
	}
	
	public function uninstall() {
		Base_AclCommon::delete_permission('Advanced User Settings');
		global $database;
		$ret = true;
		$ret &= DB::DropTable('base_user_settings');
		Base_ThemeCommon::uninstall_default_theme(Base_User_SettingsInstall::module_name());
		return $ret;
	}

	public function version() {
		return array("1.0");
	}

	public function requires($v) {
		return array(
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0),
			array('name'=>Base_UserInstall::module_name(),'version'=>0),
			array('name'=>Base_User_LoginInstall::module_name(),'version'=>0));
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>