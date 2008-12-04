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
		Base_LangCommon::install_translations($this->get_type());
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
			name C(32) NOTNULL,
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
			name C(32) NOTNULL,
			value X NOTNULL',
			array('constraints'=>', FOREIGN KEY (applet_id) REFERENCES base_dashboard_default_applets(ID), PRIMARY KEY(applet_id,name)'));
		if(!$ret){
			print('Unable to create table base_dashboard_default_settings<br>');
			return false;
		}
		$this->add_aco('set default dashboard','Super administrator');
		Base_ThemeCommon::install_default_theme($this->get_type());
		return $ret;
	}
	
	public function uninstall() {
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
			array('name'=>'Base/ActionBar','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/User','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Utils/TabbedBrowser','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0),
			array('name'=>'Libs/FCKeditor','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/ScriptAculoUs','version'=>0),
			array('name'=>'Utils/Tooltip','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Something like igoogle',
			'Author'=>'Paul Bukowski <pbukowski@telaxus.com>',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>