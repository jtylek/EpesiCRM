<?php
/**
 * Import data from csv file
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage import
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ImportInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('crm_import_company','
			id I4 KEY NOTNULL,
			original C(64),
			created_on T DEFTIMESTAMP',
			array('constraints'=>', UNIQUE(original)'));
		if(!$ret){
			print('Unable to create table crm_import_company.<br>');
			return false;
		}
		$ret &= DB::CreateTable('crm_import_contact','
			id I4 KEY NOTNULL,
			original C(64),
			created_on T DEFTIMESTAMP',
			array('constraints'=>', UNIQUE(original)'));
		if(!$ret){
			print('Unable to create table crm_import_contact.<br>');
			return false;
		}
		$ret &= DB::CreateTable('crm_import_history','
			original C(64) KEY NOTNULL,
			contact_id I4 NOTNULL,
			created_on T DEFTIMESTAMP,
			edited_on T DEFTIMESTAMP,
			created_by I4 NOTNULL',
			array('constraints'=>', FOREIGN KEY (created_by) REFERENCES user_login(id)'));
		if(!$ret){
			print('Unable to create table crm_import_history.<br>');
			return false;
		}
		$ret &= DB::CreateTable('crm_import_note','
			id I4 KEY NOTNULL,
			original C(64),
			contact_id I4 NOTNULL,
			created_on T DEFTIMESTAMP,
			created_by I4 NOTNULL',
			array('constraints'=>', FOREIGN KEY (id) REFERENCES utils_attachment_link(ID), FOREIGN KEY (created_by) REFERENCES user_login(id), UNIQUE(original)'));
		if(!$ret){
			print('Unable to create table crm_import_history.<br>');
			return false;
		}
		$ret &= DB::CreateTable('crm_import_attach','
			id I4 KEY NOTNULL,
			original C(64),
			created_on T DEFTIMESTAMP',
			array('constraints'=>', FOREIGN KEY (id) REFERENCES utils_attachment_link(ID), UNIQUE(original)'));
		if(!$ret){
			print('Unable to create table crm_import_attach.<br>');
			return false;
		}
		$ret &= DB::CreateTable('crm_import_task','
			id I4 KEY NOTNULL,
			original C(64),
			created_on T DEFTIMESTAMP',
			array('constraints'=>', UNIQUE(original)'));
		if(!$ret){
			print('Unable to create table crm_import_task.<br>');
			return false;
		}
		$ret &= DB::CreateTable('crm_import_phonecall','
			id I4 KEY NOTNULL,
			original C(64),
			created_on T DEFTIMESTAMP',
			array('constraints'=>', UNIQUE(original)'));
		if(!$ret){
			print('Unable to create table crm_import_phonecall.<br>');
			return false;
		}
		$ret &= DB::CreateTable('crm_import_event','
			id I4 KEY NOTNULL,
			original C(64),
			created_on T DEFTIMESTAMP',
			array('constraints'=>', UNIQUE(original)'));
		if(!$ret){
			print('Unable to create table crm_import_event.<br>');
			return false;
		}

		$this->create_data_dir();

		$this->add_aco('import',array('Super administrator'));

		return $ret;
	}

	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('crm_import_event');
		$ret &= DB::DropTable('crm_import_task');
		$ret &= DB::DropTable('crm_import_history');
		$ret &= DB::DropTable('crm_import_note');
		$ret &= DB::DropTable('crm_import_contact');
		$ret &= DB::DropTable('crm_import_company');
		$ret &= DB::DropTable('crm_import_attach');
		$ret &= DB::DropTable('crm_import_phonecall');
		return $ret;
	}

	public function version() {
		return array("1.0");
	}

	public function requires($v) {
		return array(
			array('name'=>'CRM/Calendar','version'=>0),
			array('name'=>'CRM/Contacts','version'=>0),
			array('name'=>'CRM/PhoneCall','version'=>0),
			array('name'=>'CRM/Tasks','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Utils/TabbedBrowser','version'=>0),
			array('name'=>'Utils/Attachment','version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'Import data from csv file',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}

	public static function simple_setup() {
		return true;
	}

}

?>
