<?php
/**
 * Activities history for Company and Contacts
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts-accountmanager
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_AccountManagerInstall extends ModuleInstall {

	public function install() {
		Utils_RecordBrowserCommon::new_record_field('company', array('name' => _M('Account Manager'), 'type'=>'crm_contact', 'param'=>array('field_type'=>'select', 'crits'=>array('CRM_Contacts_AccountManagerCommon', 'crits_accountmanager'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>false, 'filter'=>true, 'visible'=>true));
		Utils_RecordBrowserCommon::new_browse_mode_details_callback('company', 'CRM/Contacts/AccountManager', 'browse_mode_details');
		return true;
	}
	
	public function uninstall() {
		Utils_RecordBrowserCommon::delete_record_field('company', 'Account Manager');
		Utils_RecordBrowserCommon::delete_browse_mode_details_callback('company', 'CRM/Contacts/AccountManager', 'browse_mode_details');
		return true;
	}
	
	public function version() {
		return array("1.0");
	}

	public static function simple_setup() {
        return array('package'=>__('CRM'), 'option'=>__('Account Manager'));
	}
	
	public function requires($v) {
		return array(
			array('name'=>'CRM/Contacts', 'version'=>0),
		);
	}
	
	public static function info() {
		return array(
			'Description'=>'Account Manager field for Companies',
			'Author'=>'Arkadiusz Bisaga <abisaga@telaxus.com>',
			'License'=>'MIT');
	}
	
}

?>