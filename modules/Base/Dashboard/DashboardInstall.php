<?php
/**
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage dashboard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_DashboardInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('base_dashboard_tabs','
			id I4 AUTO KEY,
			user_login_id I4,
			name C(64) NOTNULL,
			pos I2',
			array('constraints'=>', FOREIGN KEY (user_login_id) REFERENCES user_login(ID)'));
		if(!$ret){
			print('Unable to create table base_dashboard_tabs.<br>');
			return false;
		}
		$ret &= DB::CreateTable('base_dashboard_default_tabs','
			id I4 AUTO KEY,
			name C(64) NOTNULL,
			pos I2');
		if(!$ret){
			print('Unable to create table base_dashboard_default_tabs.<br>');
			return false;
		}
		DB::Execute('INSERT INTO base_dashboard_default_tabs(name,pos) VALUES(\'Default\',0)');
		$ret &= DB::CreateTable('base_dashboard_applets','
			id I4 AUTO KEY,
			user_login_id I4,
			module_name C(128),
			col I2 DEFAULT 0,
			pos I2 DEFAULT 0,
			color I2 DEFAULT 0,
			tab I4',
			array('constraints'=>', FOREIGN KEY (user_login_id) REFERENCES user_login(ID), FOREIGN KEY (tab) REFERENCES base_dashboard_tabs(ID)'));
		if(!$ret){
			print('Unable to create table base_dashboard_applets.<br>');
			return false;
		}
		$ret &= DB::CreateTable('base_dashboard_settings','
			applet_id I4,
			name C(64) NOTNULL,
			value X NOTNULL',
			array('constraints'=>', FOREIGN KEY (applet_id) REFERENCES base_dashboard_applets(ID), PRIMARY KEY(applet_id,name)'));
		if(!$ret){
			print('Unable to create table base_dashboard_settings.<br>');
			return false;
		}
		$ret &= DB::CreateTable('base_dashboard_default_applets','
			id I4 AUTO KEY,
			module_name C(128),
			col I2 DEFAULT 0,
			pos I2 DEFAULT 0,
			color I2 DEFAULT 0,
			tab I4',
			array('constraints'=>', FOREIGN KEY (tab) REFERENCES base_dashboard_default_tabs(ID)'));
		if(!$ret){
			print('Unable to create table base_dashboard_default_applets.<br>');
			return false;
		}
		$ret &= DB::CreateTable('base_dashboard_default_settings','
			applet_id I4,
			name C(64) NOTNULL,
			value X NOTNULL',
			array('constraints'=>', FOREIGN KEY (applet_id) REFERENCES base_dashboard_default_applets(ID), PRIMARY KEY(applet_id,name)'));
		if(!$ret){
			print('Unable to create table base_dashboard_default_settings<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme($this->get_type());

		Base_AclCommon::add_permission(_M('Dashboard'),array('ACCESS:employee'));
		Base_AclCommon::add_permission(_M('Dashboard - manage applets'),array('ACCESS:employee'));
		Base_HomePageCommon::set_home_page(_M('Dashboard'),array('ACCESS:employee'));
		Base_HomePageCommon::set_home_page(_M('My Contact'),array()); // Not exactly the place to add that, but we need to ensure proper order of home pages

		return $ret;
	}

	public function uninstall() {
		Base_AclCommon::delete_permission('Dashboard');
		Base_AclCommon::delete_permission('Dashboard - manage applets');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		$ret = true;
		$ret &= DB::DropTable('base_dashboard_settings');
		$ret &= DB::DropTable('base_dashboard_applets');
		$ret &= DB::DropTable('base_dashboard_default_settings');
		$ret &= DB::DropTable('base_dashboard_default_applets');
		$ret &= DB::DropTable('base_dashboard_users');
		return $ret;
	}

	public function version() {
		return array("1.0");
	}

	public function requires($v) {
		return array(
			array('name'=>Base_ActionBarInstall::module_name(),'version'=>0),
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Base_HomePageInstall::module_name(),'version'=>0),
			array('name'=>Base_UserInstall::module_name(),'version'=>0),
			array('name'=>Base_User_SettingsInstall::module_name(),'version'=>0),
			array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0),
			array('name'=>Utils_TabbedBrowserInstall::module_name(),'version'=>0),
			array('name'=>Utils_GenericBrowserInstall::module_name(),'version'=>0),
			array('name'=>Libs_CKEditorInstall::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Libs_ScriptAculoUsInstall::module_name(),'version'=>0),
			array('name'=>Utils_TooltipInstall::module_name(),'version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'Something like igoogle',
			'Author'=>'Paul Bukowski <pbukowski@telaxus.com>',
			'License'=>'MIT');
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}

}

?>
