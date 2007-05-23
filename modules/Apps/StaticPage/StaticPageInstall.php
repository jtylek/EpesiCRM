<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_StaticPageInstall extends ModuleInstall {

	public static function install() {
		$ret = true;
		$ret &= DB::CreateTable('apps_staticpage_pages','
			id I AUTO KEY,
			path C(255) UNIQUE NOTNULL,
			title C(255) NOTNULL,
			content X',
			array('constraints'=>''));
		if(!$ret){
			print('Unable to create table pages.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme('Apps/StaticPage');
		return $ret;
	}
	
	public static function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('apps_staticpage_pages');
		Base_ThemeCommon::uninstall_default_theme('Apps/StaticPage');
		return $ret;
	}
	
}

?>