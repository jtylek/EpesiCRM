<?php
/**
 * CRMHRInstall class.
 * 
 * This class provides initialization data for CRMHR module.
 * 
 * @author Kuba SĹawiĹski <ruud@o2.pl>, Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-extra
 */
defined("_VALID_ACCESS") || die();

/**
 * This class provides initialization data for Test module.
 * @package tcms-extra
 * @subpackage test
 */
class CRM_ContactsInstall extends ModuleInstall {
	public function install() {
// ************ contacts ************** //
		Base_ThemeCommon::install_default_theme('CRM/Contacts');
		$fields = array(
			array('name'=>'Login', 'type'=>'integer', 'required'=>false, 'param'=>'64', 'extra'=>false, 'display_callback'=>array('CRM_ContactsCommon', 'display_login'), 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_login')),
			array('name'=>'First Name', 'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true),
			array('name'=>'Last Name', 'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true),
			array('name'=>'Address 1', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Address 2', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Email', 'type'=>'text', 'required'=>false, 'param'=>'128', 'extra'=>false, 'visible'=>false, 'display_callback'=>array('CRM_ContactsCommon', 'display_email'), 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_email')),
			array('name'=>'City', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>true),
			array('name'=>'Country', 'type'=>'commondata', 'required'=>true, 'param'=>array('Countries'), 'extra'=>false, 'visible'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_country')),
			array('name'=>'Zone', 'type'=>'commondata', 'required'=>false, 'param'=>array('Countries','Country'), 'extra'=>false, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_zone')),
			array('name'=>'Postal Code', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Phone', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>true),
			array('name'=>'Fax', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Web address', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'display_callback'=>array('CRM_ContactsCommon', 'display_webaddress'), 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_webaddress')),
			array('name'=>'Company Name', 'type'=>'multiselect', 'required'=>false, 'param'=>array('company'=>'Company Name'), 'extra'=>false, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_company')),
			array('name'=>'Group', 'type'=>'multiselect', 'required'=>false, 'param'=>'Contacts_groups', 'extra'=>false)
		);
		Utils_RecordBrowserCommon::install_new_recordset('contact', $fields);
		Utils_RecordBrowserCommon::set_tpl('contact', Base_ThemeCommon::get_template_filename('CRM/Contacts', 'Contact'));
		Utils_RecordBrowserCommon::set_processing_method('contact', array('CRM_ContactsCommon', 'submit_contact'));
		Utils_RecordBrowserCommon::new_filter('contact', 'Company');
		Utils_RecordBrowserCommon::set_quickjump('contact', 'Last Name');
		Utils_RecordBrowserCommon::set_favorites('contact', true);
		Utils_RecordBrowserCommon::set_recent('contact', 15);
		Utils_RecordBrowserCommon::set_caption('contact', 'Contacts');
//		Utils_RecordBrowserCommon::set_tpl('contact', Base_ThemeCommon::get_template_filename('CRM/Contacts', 'View_entry'));
// ************ companies ************** //
		$fields = array(
			array('name'=>'Company Name', 'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true),
			array('name'=>'Short Name', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>false),
			array('name'=>'Address 1', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Address 2', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'City', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>true),
			array('name'=>'Country', 'type'=>'commondata', 'required'=>true, 'param'=>array('Countries'), 'extra'=>false, 'visible'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_country')),
			array('name'=>'Zone', 'type'=>'commondata', 'required'=>false, 'param'=>array('Countries','Country'), 'extra'=>false, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_zone')),
			array('name'=>'Postal Code', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Phone', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>true),
			array('name'=>'Fax', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Web address', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'display_callback'=>array('CRM_ContactsCommon', 'display_webaddress'), 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_webaddress'), 'visible'=>true),
			array('name'=>'Group', 'type'=>'multiselect', 'required'=>false, 'param'=>'Companies_groups', 'extra'=>false)
		);
		Utils_RecordBrowserCommon::set_tpl('company', Base_ThemeCommon::get_template_filename('CRM/Contacts', 'Company'));
		Utils_RecordBrowserCommon::install_new_recordset('company', $fields);
		Utils_RecordBrowserCommon::set_quickjump('company', 'Name');
		Utils_RecordBrowserCommon::set_favorites('company', true);
		Utils_RecordBrowserCommon::set_recent('company', 15);
		Utils_RecordBrowserCommon::set_caption('company', 'Companies');
// ************ addons ************** //
		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Contacts', 'company_addon', 'Contacts');
// ************ other ************** //
		Utils_CommonDataCommon::new_array('Companies_Groups',array('Customer','Vendor','Other'));
		Utils_CommonDataCommon::new_array('Contacts_Groups',array('Public','Private','Other'));
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Contacts');
		Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Contacts', 'company_addon');
		Utils_RecordBrowserCommon::uninstall_recordset('company');
		Utils_RecordBrowserCommon::uninstall_recordset('contact');
		Utils_CommonDataCommon::remove('Contacts_Groups');
		return true;
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Utils/RecordBrowser', 'version'=>0),
			array('name'=>'Data/Countries', 'version'=>0) 
		);
	}
	
	public function provides($v) {
		return array();
	}
	
	public static function info() {
		return array('Author'=>'<a href="mailto:kslawinski@telaxus.com">Kuba Sławiński</a> and <a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Module for organising Your contacts.');
	}
	
	public static function simple_setup() {
		return true;
	}
	
	public function version() {
		return array('0.9');
	}
	
	public static function post_install() {
		return array(array('type'=>'select','name'=>'first','label'=>'First name','default'=>'0','values'=>array('x','y')),array('type'=>'text','name'=>'lastn','label'=>'Last name','default'=>'x'),
			     array('type'=>'group', 'label'=>'radio','elems'=>array(
				array('type'=>'checkbox','label'=>'c1_l','name'=>'c1','values'=>'c1_t','default'=>0),
				array('type'=>'checkbox','label'=>'c2_l','name'=>'c2','values'=>'c2_t','default'=>0))
				   ));
	}

	public static function post_install_process($val) {
		print_r($val);
	}
}

?>
