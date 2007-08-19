<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-utils
 * @subpackage custom-menu
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CustomMenuInstall extends ModuleInstall {

	public static function install() {
		$ret = true;
		$ret &= DB::CreateTable('utils_custommenu_page','
			id C(32) KEY NOTNULL,
			module C(128) NOTNULL,
			function C(128),
			arguments X',
			array('constraints'=>', FOREIGN KEY (module) REFERENCES modules(name)'));
		if(!$ret){
			print('Unable to create table utils_custommenu_page.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_custommenu_entry','
			page_id C(32) NOTNULL,
			path C(255) KEY NOTNULL',
			array('constraints'=>', FOREIGN KEY (page_id) REFERENCES utils_custommenu_page(id)'));
		if(!$ret){
			print('Unable to create table utils_custommenu_entry.<br>');
			return false;
		}
		return $ret;
	}
	
	public static function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('utils_custommenu_entry');
		$ret &= DB::DropTable('utils_custommenu_page');
		return $ret;
	}
	
	public static function version() {
		return array('1.0.0');
	}
	public static function requires_0() {
		return array(
			array('name'=>'Base/Lang','version'=>0));
	}
}

?>