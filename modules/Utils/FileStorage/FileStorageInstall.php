<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Utils
 * @subpackage FileStorage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileStorageInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('utils_filestorage_files','
			id I8 AUTO KEY,
			filename C(256) NOTNULL,
			uploaded_on T NOTNULL,
			hash C(128) NOTNULL',
			array('constraints'=>', UNIQUE(hash)'));
		if(!$ret){
			print('Unable to create table utils_filestorage_files.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_filestorage_link','
			storage_id I8 NOTNULL,
			link C(128) KEY',
			array('constraints'=>', FOREIGN KEY (storage_id) REFERENCES utils_filestorage_files(id)'));
		if(!$ret){
			print('Unable to create table utils_filestorage_link.<br>');
			return false;
		}
		$this->create_data_dir();
		file_put_contents($this->get_data_dir().'.htaccess','deny from all');
		return $ret;
	}
	
	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('utils_filestorage_link');
		$ret &= DB::DropTable('utils_filestorage_files');
		return $ret;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array();
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>