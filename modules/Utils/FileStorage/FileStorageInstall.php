<?php
/**
 *
 * @author j@epe.si
 * @copyright Janusz Tylek
 * @license MIT
 * @version 0.1
 * @package epesi-Utils
 * @subpackage FileStorage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileStorageInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		
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
	        file_id I8 NOTNULL,
			token C(128) NOTNULL,
            created_on T NOTNULL,
            created_by I8 NOTNULL,
            expires_on T',
			['constraints' => ', FOREIGN KEY (file_id) REFERENCES utils_filestorage(id)']);
		if (!$ret) {
			print('Unable to create table utils_filestorage_remote.<br>');
			return false;
		}

		$ret &= DB::CreateTable('utils_filestorage_access', '
			id I8 AUTO KEY,
	        file_id I8 NOTNULL,
			date_accessed T NOTNULL,
			accessed_by I8 NOTNULL,
            type I8 NOTNULL,
            ip_address C(32),
			host_name C(64)',
			['constraints' => ', FOREIGN KEY (file_id) REFERENCES utils_filestorage(id)']);
		if (!$ret) {
			print('Unable to create table utils_filestorage_access.<br>');
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
		$ret &= DB::DropTable('utils_filestorage_remote');
		$ret &= DB::DropTable('utils_filestorage_access');
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
			'Author'=>'j@epe.si',
			'License'=>'MIT');
	}

	public static function simple_setup() {
		return false;
	}

}

?>