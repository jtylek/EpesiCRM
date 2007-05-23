<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SQLTableBrowser_PeopleInstall extends ModuleInstall {

	public static function install() {
		$ret = true;
		$ret &= DB::CreateTable('People','
			id I4 AUTO KEY,
			fname C(32),
			lname C(32),
			company I4 NOTNULL',
			array('constraints'=>''));
		if(!$ret){
			print('Unable to create table People.<br>');
			return false;
		}
		return $ret;
	}
	
	public static function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('People');
		return $ret;
	}
}

?>