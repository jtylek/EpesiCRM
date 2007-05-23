<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Theme_AdministratorCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return "Change theme";
	}	

	public static function body_access() {
		return Base_AclCommon::i_am_admin();
	}
}
?>