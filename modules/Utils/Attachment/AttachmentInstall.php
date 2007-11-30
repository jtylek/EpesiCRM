<?php
/**
 * Use this module if you want to add attachments to some page.
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package utils-attachment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_AttachmentInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('utils_attachment_link','
			id I4 AUTO KEY NOTNULL,
			local C(255) NOTNULL,
			deleted I1 DEFAULT 0,
			attachment_key C(32) NOTNULL');
		if(!$ret){
			print('Unable to create table utils_attachment_link.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_attachment_file','
			attach_id I4 NOTNULL,
			original C(255) NOTNULL,
			created_by I4,
			created_on T DEFTIMESTAMP,
			revision I4 NOTNULL');
		if(!$ret){
			print('Unable to create table utils_attachment_file.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_attachment_note','
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
		$this->create_data_dir();
		Base_ThemeCommon::install_default_theme($this->get_type());
		return $ret;
	}

	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('utils_attachment_note');
		$ret &= DB::DropTable('utils_attachment_file');
		$ret &= DB::DropTable('utils_attachment_link');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return $ret;
	}

	public function version() {
		return array("0.1");
	}

	public function requires($v) {
		return array(array('name'=>'Utils/GenericBrowser','version'=>0),
			     array('name'=>'Utils/FileUpload', 'version'=>0),
			     array('name'=>'Libs/QuickForm', 'version'=>0),
			     array('name'=>'Libs/FCKeditor', 'version'=>0),
			     array('name'=>'Libs/Leigthbox', 'version'=>0),
			     array('name'=>'Utils/Tooltip', 'version'=>0),
			     array('name'=>'Base/RegionalSettings', 'version'=>0),
			     array('name'=>'Base/Box', 'version'=>0),
			     array('name'=>'Base/Theme', 'version'=>0),
			     array('name'=>'Base/ActionBar', 'version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'Use this module if you want to add attachments to some page.',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}

	public static function simple_setup() {
		return false;
	}

}

?>
