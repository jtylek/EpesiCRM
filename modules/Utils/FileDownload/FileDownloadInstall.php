<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package utils
 * @subpackage file-download
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileDownloadInstall extends ModuleInstall {

	public static function install() {
		$ret = true;
		$ret &= DB::CreateTable('utils_filedownload_files','
			id I4 AUTO KEY,
			path X,
			curr I8 DEFAULT 0,
			size I8 DEFAULT 0,
			rate F DEFAULT 0,
			time F DEFAULT 0,
			view_time F DEFAULT 0,
			posted_on T',
			array('constraints'=>''));
		if(!$ret){
			print('Unable to create table utils_filedownload_files.<br>');
			return false;
		}
		return $ret;
	}
	
	public static function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('utils_filedownload_files');
		return $ret;
	}
	public static function version() {
		return array("0.1");
	}
	
	public static function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0));
	}
}

?>