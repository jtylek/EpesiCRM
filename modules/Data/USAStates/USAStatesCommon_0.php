<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Data_USAStatesCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return "USA States";
	}
	
	public static function & get() {
		return Utils_CommonDataCommon::get_array('USA States');
	}
}

?>