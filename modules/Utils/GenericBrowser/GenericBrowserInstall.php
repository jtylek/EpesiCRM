<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GenericBrowserInstall extends ModuleInstall {

	public static function install() {
		$ret = true;
		$ret &= DB::CreateTable('generic_browser',"name C(40) NOTNULL, column_id I NOTNULL, column_pos I NOTNULL, display I1 DEFAULT 1", array('constraints' => ', PRIMARY KEY (name,column_id)'));
		if(!$ret){
			print('Unable to create table generic_browser.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme('Utils/GenericBrowser');
		return $ret;
	}
	
	public static function uninstall() {
		global $database;
		$ret = true;
		$ret &= DB::DropTable('generic_browser');
		Base_ThemeCommon::uninstall_default_theme('Utils/GenericBrowser');
		return true;
	}
	
}

?>