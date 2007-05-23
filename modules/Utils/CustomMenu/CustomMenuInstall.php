<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CustomMenuInstall extends ModuleInstall {

	public static function install() {
		$ret = true;
		$ret &= DB::CreateTable('utils_custommenu_entry','
			id C(32) NOTNULL,
			path C(255) KEY NOTNULL,
			module C(128) NOTNULL,
			function C(128),
			arguments X',
			array('constraints'=>', FOREIGN KEY (module) REFERENCES modules(name)'));
		if(!$ret){
			print('Unable to create table utils_custommenu_entry.<br>');
			return false;
		}
		return $ret;
	}
	
	public static function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('utils_custommenu_entry');
		return $ret;
	}
	
}

?>