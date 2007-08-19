<?php
/**
 * User_Settings class.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage user-settings
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_SettingsInstall extends ModuleInstall {

	public static function install() {
		global $database;
		$ret = true;
		$ret &= DB::CreateTable('base_user_settings','
			user_login_id I4 NOTNULL,
			module C(64) NOTNULL,
			variable C(32) NOTNULL,
			value C(128) NOTNULL',
			array('constraints'=>', FOREIGN KEY (user_login_id) REFERENCES user_login(id), PRIMARY KEY(user_login_id,module,variable)'));
		if(!$ret){
			print('Unable to create table base_user_settings.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme('Base/User/Settings');

		return $ret;
	}
	
	public static function uninstall() {
		global $database;
		$ret = true;
		$ret &= DB::DropTable('base_user_settings');
		Base_ThemeCommon::uninstall_default_theme('Base/User/Settings');
		return $ret;
	}

	public static function version() {
		return array("1.0");
	}

	public static function requires_0() {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/User','version'=>0),
			array('name'=>'Base/User/Login','version'=>0));
	}
}

?>