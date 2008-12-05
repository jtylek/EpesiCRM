<?php
/**
 * Use this module if you want to add attachments to some page.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_AttachmentInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		$ret = true;
		$ret &= DB::CreateTable('utils_attachment_link','
			id I4 AUTO KEY NOTNULL,
			local C(255) NOTNULL,
			deleted I1 DEFAULT 0,
			permission I2 DEFAULT 0,
			permission_by I4,
			sticky I1 DEFAULT 0,
			func C(255),
			args C(255)',
			array('constraints'=>', FOREIGN KEY (permission_by) REFERENCES user_login(ID)'));
		if(!$ret){
			print('Unable to create table utils_attachment_link.<br>');
			return false;
		}
		DB::CreateIndex('utils_attachment_link__attachment__local__idx', 'utils_attachment_link', 'local');
		$ret &= DB::CreateTable('utils_attachment_file','
			id I4 AUTO KEY NOTNULL,
			attach_id I4 NOTNULL,
			original C(255) NOTNULL,
			created_by I4,
			created_on T DEFTIMESTAMP,
			revision I4 NOTNULL',
			array('constraints'=>', UNIQUE(attach_id,revision), FOREIGN KEY (created_by) REFERENCES user_login(ID), FOREIGN KEY (attach_id) REFERENCES utils_attachment_link(id)'));
		if(!$ret){
			print('Unable to create table utils_attachment_file.<br>');
			return false;
		}
		DB::CreateIndex('utils_attachment_file__revision__idx', 'utils_attachment_file', 'revision');
		$ret &= DB::CreateTable('utils_attachment_download','
			id I4 AUTO KEY NOTNULL,
			attach_file_id I4 NOTNULL,
			created_by I4,
			created_on T,
			remote I1 DEFAULT 0,
			download_on T DEFTIMESTAMP,
			ip_address C(32),
			host_name C(64),
			description C(128),
			token C(32)',
			array('constraints'=>', FOREIGN KEY (created_by) REFERENCES user_login(ID), FOREIGN KEY (attach_file_id) REFERENCES utils_attachment_file(id)'));
		if(!$ret){
			print('Unable to create table utils_attachment_download.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_attachment_note','
			id I4 AUTO KEY NOTNULL,
			attach_id I4 NOTNULL,
			text X NOTNULL,
			created_by I4,
			created_on T DEFTIMESTAMP,
			revision I4 NOTNULL',
			array('constraints'=>', UNIQUE(attach_id,revision), FOREIGN KEY (created_by) REFERENCES user_login(ID), FOREIGN KEY (attach_id) REFERENCES utils_attachment_link(id)'));
		if(!$ret){
			print('Unable to create table utils_attachment_note.<br>');
			return false;
		}
		DB::CreateIndex('utils_attachment_note__revision__idx', 'utils_attachment_note', 'revision');
		$this->add_aco('view download history','Employee');
		$this->create_data_dir();
		file_put_contents($this->get_data_dir().'.htaccess','deny from all');
		Base_ThemeCommon::install_default_theme($this->get_type());
		return $ret;
	}

	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('utils_attachment_note');
		$ret &= DB::DropTable('utils_attachment_download');
		$ret &= DB::DropTable('utils_attachment_file');
		$ret &= DB::DropTable('utils_attachment_link');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return $ret;
	}

	public function version() {
		return array("1.0");
	}

	public function requires($v) {
		return array(array('name'=>'Utils/GenericBrowser','version'=>0),
			     array('name'=>'Utils/FileUpload', 'version'=>0),
			     array('name'=>'Utils/BBCode', 'version'=>0),
			     array('name'=>'Libs/QuickForm', 'version'=>0),
			     array('name'=>'Libs/FCKeditor', 'version'=>0),
			     array('name'=>'Libs/Leightbox', 'version'=>0),
			     array('name'=>'Utils/Tooltip', 'version'=>0),
			     array('name'=>'Utils/Attachment/Administrator', 'version'=>0),
			     array('name'=>'Utils/Watchdog', 'version'=>0),
			     array('name'=>'Base/RegionalSettings', 'version'=>0),
			     array('name'=>'Base/Lang','version'=>0),
			     array('name'=>'Base/Box', 'version'=>0),
			     array('name'=>'Base/Theme', 'version'=>0),
			     array('name'=>'Base/ActionBar', 'version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'Use this module if you want to add attachments to some page.',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}

	public static function simple_setup() {
		return false;
	}

}

?>
