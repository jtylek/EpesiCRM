<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SQLTableBrowser_CompaniesInstall extends ModuleInstall {

	public static function install() {
		$ret = true;
		$ret &= DB::CreateTable('companies','
			id I4 AUTO KEY,
			name C(32)',
			array('constraints'=>''));
		if(!$ret){
			print('Unable to create table companies.<br>');
			return false;
		}
		return $ret;
	}
	
	public static function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('companies');
		return $ret;
	}
}

?>