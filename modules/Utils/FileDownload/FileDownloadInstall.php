<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage FileDownload
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileDownloadInstall extends ModuleInstall {

	public function install() {
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
		$this->create_data_dir();
		return $ret;
	}
	
	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('utils_filedownload_files');
		return $ret;
	}
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0));
	}
}

?>