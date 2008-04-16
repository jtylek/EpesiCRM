<?php
/**
 * Import data from csv file
 * @author shacky@poczta.fm
 * @copyright shacky@poczta.fm
 * @license SPL
 * @version 0.1
 * @package crm-import
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ImportInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('crm_import_company','
			id I4 KEY NOTNULL,
			original C(64),
			created_on T DEFTIMESTAMP',
			array('constraints'=>', FOREIGN KEY (id) REFERENCES company(ID), UNIQUE(original)'));
		if(!$ret){
			print('Unable to create table crm_import_company.<br>');
			return false;
		}
		$ret &= DB::CreateTable('crm_import_contact','
			id I4 KEY NOTNULL,
			original C(64),
			created_on T DEFTIMESTAMP',
			array('constraints'=>', FOREIGN KEY (id) REFERENCES contact(ID), UNIQUE(original)'));
		if(!$ret){
			print('Unable to create table crm_import_contact.<br>');
			return false;
		}
		$this->add_aco('import',array('Super administrator'));

		return $ret;
	}
	
	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('crm_import_contact');
		$ret &= DB::DropTable('crm_import_company');
		return $ret;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'CRM/Calendar','version'=>0),
			array('name'=>'CRM/Contacts','version'=>0),
			array('name'=>'CRM/PhoneCall','version'=>0),
			array('name'=>'CRM/Tasks','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Utils/Attachment','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Import data from csv file',
			'Author'=>'shacky@poczta.fm',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>