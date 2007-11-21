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
			deleted I1,
			attachment_key C(32) NOTNULL');
		if(!$ret){
			print('Unable to create table utils_attachment_link.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_attachment_comment','
			comment_id I4 NOTNULL,
			text X NOTNULL,
			created_by I4,
			created_on T DEFTIMESTAMP,
			revision I4 NOTNULL',
			array('constraints'=>', UNIQUE(comment_id,revision), FOREIGN KEY (created_by) REFERENCES user_login(ID), FOREIGN KEY (comment_id) REFERENCES utils_attachment_link(id)'));
		if(!$ret){
			print('Unable to create table utils_attachment_comment.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_attachment_file','
			comment_id I4 NOTNULL,
			id I4 AUTO KEY NOTNULL,
			original C(255) NOTNULL,
			deleted I1',
			array('constraints'=>', FOREIGN KEY (comment_id) REFERENCES utils_attachment_link(id)'));
		if(!$ret){
			print('Unable to create table utils_attachment_file.<br>');
			return false;
		}
		return $ret;
	}

	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('utils_attachment_file');
		$ret &= DB::DropTable('utils_attachment_comment');
		$ret &= DB::DropTable('utils_attachment_link');
		return $ret;
	}

	public function version() {
		return array("0.1");
	}

	public function requires($v) {
		return array(array('name'=>'Utils/GenericBrowser','version'=>0),
			     array('name'=>'Utils/FileUpload', 'version'=>0),
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
