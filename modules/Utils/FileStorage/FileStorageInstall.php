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

        $ret &= DB::CreateTable('utils_filestorage_files', '
			id I8 AUTO KEY,
			hash C(128) NOTNULL,
            deleted I1 DEFAULT 0,
            size I8,
            type C(256)',
            array('constraints' => ', UNIQUE(hash)'));
        if (!$ret) {
            print('Unable to create table utils_filestorage_files.<br>');
            return false;
        }

        $ret &= DB::CreateTable('utils_filestorage', '
            id I8 AUTO KEY,
            filename C(256) NOTNULL,
            link C(128),
            backref C(128),
            created_on T NOTNULL,
            created_by I8 NOTNULL,
            deleted I1 DEFAULT 0,
            file_id I8 NOTNULL',
            array('constraints' => ', FOREIGN KEY (file_id) REFERENCES utils_filestorage_files(id)')
        );
        if (!$ret) {
            print('Unable to create table utils_filestorage.<br>');
            return false;
        }

		$ret &= DB::CreateTable('utils_filestorage_remote', '
			id I8 AUTO KEY,
			token C(128) NOTNULL,
            created_on T NOTNULL,
            created_by I8 NOTNULL,
            expires_on T,
            times_downloaded I8',
			['constraints' => ', FOREIGN KEY (id) REFERENCES utils_filestorage(id)']);
		if (!$ret) {
			print('Unable to create table utils_filestorage_remote.<br>');
			return false;
		}

        $this->create_data_dir();
        file_put_contents($this->get_data_dir() . '.htaccess', 'deny from all');
        return $ret;
	}

	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('utils_filestorage');
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