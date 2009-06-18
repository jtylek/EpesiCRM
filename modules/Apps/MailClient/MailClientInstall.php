<?php
/**
 * Simple mail client
 * @author pbukowski@telaxus.com
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage mailclient
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_MailClientInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		$ret = true;
		$ret &= DB::CreateTable('apps_mailclient_accounts','
			id I4 AUTO KEY,
			user_login_id I4 NOTNULL,
			login C(127) NOTNULL,
			mail C(255) NOTNULL,
			password C(127) NOTNULL,

			incoming_server C(255) NOTNULL,
			incoming_protocol I1 NOTNULL,
			incoming_method C(15) DEFAULT \'auto\',
			incoming_ssl I1 DEFAULT 0,
			pop3_leave_msgs_on_server I2 DEFAULT 0,

			smtp_login C(127),
			smtp_password C(127),
			smtp_server C(255),
			smtp_auth I1 DEFAULT 1,
			smtp_ssl I1 DEFAULT 0',
			array('constraints'=>', FOREIGN KEY (user_login_id) REFERENCES user_login(ID)'));
		if(!$ret){
			print('Unable to create table apps_mailclient_accounts.<br>');
			return false;
		}
		
		$ret &= DB::CreateTable('apps_mailclient_filters','
			id I4 AUTO KEY,
			account_id I4 NOTNULL,
			name C(64),
			match_method I1 DEFAULT 0',
			array('constraints'=>', FOREIGN KEY (account_id) REFERENCES apps_mailclient_accounts(ID)'));
		if(!$ret){
			print('Unable to create table apps_mailclient_filters.<br>');
			return false;
		}

		$ret &= DB::CreateTable('apps_mailclient_filter_rules','
			id I4 AUTO KEY,
			filter_id I4 NOTNULL,
			header C(64),
			rule I1 DEFAULT 0,
			value C(128)',
			array('constraints'=>', FOREIGN KEY (filter_id) REFERENCES apps_mailclient_filters(ID)'));
		if(!$ret){
			print('Unable to create table apps_mailclient_filter_rules.<br>');
			return false;
		}

		$ret &= DB::CreateTable('apps_mailclient_filter_actions','
			id I4 AUTO KEY,
			filter_id I4 NOTNULL,
			action I1 DEFAULT 0,
			value C(128)',
			array('constraints'=>', FOREIGN KEY (filter_id) REFERENCES apps_mailclient_filters(ID)'));
		if(!$ret){
			print('Unable to create table apps_mailclient_filter_actions.<br>');
			return false;
		}
		
		Base_ThemeCommon::install_default_theme($this -> get_type());
		Variable::set('max_mail_size',5*1024*1024);
		$this->create_data_dir();
//		mkdir($this->get_data_dir().'tmp');
		return $ret;
	}
	
	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('apps_mailclient_filter_rules');
		$ret &= DB::DropTable('apps_mailclient_filter_actions');
		$ret &= DB::DropTable('apps_mailclient_filters');
		$ret &= DB::DropTable('apps_mailclient_accounts');
		Variable::delete('max_mail_size');
		Base_ThemeCommon::uninstall_default_theme($this -> get_type());
		return $ret;
	}
	
	public function version() {
		return array("0.4");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/ActionBar','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/Mail','version'=>0),
			array('name'=>'Base/RegionalSettings','version'=>0),
			array('name'=>'Base/User','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0),
			array('name'=>'Utils/Tooltip','version'=>0),
			array('name'=>'Utils/Tree','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Simple mail client',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>