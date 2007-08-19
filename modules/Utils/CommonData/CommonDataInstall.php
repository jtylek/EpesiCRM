<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-utils
 * @subpackage common-data
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CommonDataInstall extends ModuleInstall {

	public static function install() {
		$ret = true;
		$ret &= DB::CreateTable('utils_commondata_arrays','
			id I4 AUTO KEY,
			name C(32) NOTNULL',
			array('constraints'=>''));
		if(!$ret){
			print('Unable to create table utils_commondata_arrays.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_commondata_data','
			array_id I4 NOT NULL,
			akey C(64),
			value X',
			array('constraints'=>', FOREIGN KEY (array_id) REFERENCES utils_commondata_arrays(id), PRIMARY KEY (array_id, akey)'));
		if(!$ret){
			print('Unable to create table utils_commondata_data.<br>');
			return false;
		}
		return $ret;
	}
	
	public static function uninstall() {
		global $database;
		$ret = true;
		$ret &= DB::DropTable('utils_commondata_arrays');
		$ret &= DB::DropTable('utils_commondata_data');
		return $ret;
	}
	public static function requires_0() {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Acl','version'=>0),
			array('name'=>'Base/Admin','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0),
			array('name'=>'Utils/Wizard','version'=>0));
	}
}

?>