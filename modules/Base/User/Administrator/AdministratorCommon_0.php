<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');


class Base_User_AdministratorCommon extends Base_AdminModuleCommon {
	public static function user_settings() {
		if(Base_AclCommon::i_am_user()) return array('Account'=>'callbody');
		return array();
	}
	
	public static function admin_caption() {
		return 'Manage users';
	}
	
}
?>