<?php
/**
 * Adds parent company field to companies.
 * @author shacky@poczta.fm
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM/Contacts
 * @subpackage ParentCompany
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_ParentCompanyInstall extends ModuleInstall {

	public function install() {
        Utils_RecordBrowserCommon::new_record_field('company', 
	    		array('name' => _M('Parent Company'),	'type'=>'crm_company', 'param'=>array('field_type'=>'select','crits'=>array('CRM_Contacts_ParentCompanyCommon','parent_company_crits')), 'required'=>false, 'extra'=>false, 'visible'=>true, 'filter'=>true,'position'=>'Phone'));
		Utils_RecordBrowserCommon::new_addon('company', 'CRM_Contacts_ParentCompany', 'parent_company_addon', _M('Child Companies'));
		return true;
	}
	
	public function uninstall() {
		Utils_RecordBrowserCommon::delete_addon('company', 'CRM_Contacts_ParentCompany', 'parent_company_addon');
        Utils_RecordBrowserCommon::delete_record_field('company', 'Parent Company');
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'CRM/Contacts','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Adds parent company field to companies.',
			'Author'=>'shacky@poczta.fm',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
        return array('package'=>__('CRM'), 'option'=>__('Parent Company'));
	}
	
}

?>