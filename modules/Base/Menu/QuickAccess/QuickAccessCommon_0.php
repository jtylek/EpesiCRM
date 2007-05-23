<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Menu_QuickAccessCommon {
	public static function user_settings() {
		if(Base_AclCommon::i_am_user()) return array('Quick access'=>'callbody');
		return array();
	} 
}

?>
