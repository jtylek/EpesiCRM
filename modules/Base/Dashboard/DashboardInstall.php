<?php
/** 
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @version 0.1
 * @package epesi-base-extra
 * @subpackage dashboard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_DashboardInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('base_dashboard_applets','
			id I4 AUTO KEY,
			user_login_id I4,
			module_name C(128),
			col I2 DEFAULT 0,
			pos I2 DEFAULT 0',
			array('constraints'=>', FOREIGN KEY (user_login_id) REFERENCES user_login(ID)'));
		if(!$ret){
			print('Unable to create table base_dashboard_applets.<br>');
			return false;
		}
		$ret &= DB::CreateTable('base_dashboard_settings','
			applet_id I4,
			name C(32) NOTNULL,
			value C(128) NOTNULL',
			array('constraints'=>', FOREIGN KEY (applet_id) REFERENCES base_dashboard_applets(ID), PRIMARY KEY(applet_id,name)'));
		if(!$ret){
			print('Unable to create table base_dashboard_applets.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme($this->get_type());
		return $ret;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		$ret = true;
		$ret &= DB::DropTable('base_dashboard_settings');
		$ret &= DB::DropTable('base_dashboard_applets');
		return $ret;
	}
	
	public function version() {
		return array("0.8.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/ActionBar','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/User','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/ScriptAculoUs','version'=>0),
			array('name'=>'Utils/Tooltip','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Something like igoogle',
			'Author'=>'Paul Bukowski <pbukowski@telaxus.com>',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>