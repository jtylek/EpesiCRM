<?php
/**
 * Simple mail client
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_MailClientInstall extends ModuleInstall {

	public function install() {
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

			smtp_server C(255),
			smtp_auth I1 DEFAULT 1,

			smtp_ssl I1 DEFAULT 0,
			incoming_ssl I1 DEFAULT 0',
			array('constraints'=>', FOREIGN KEY (user_login_id) REFERENCES user_login(ID)'));
		if(!$ret){
			print('Unable to create table apps_mailclient_accounts.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme($this -> get_type());
		$this->create_data_dir();
		mkdir($this->get_data_dir().'tmp');
		return $ret;
	}
	
	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('apps_mailclient_accounts');
		Base_ThemeCommon::uninstall_default_theme($this -> get_type());
		return $ret;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/ActionBar','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
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
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>