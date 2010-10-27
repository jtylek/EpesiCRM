<?php
/**
 * CRM Phone Call Class
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage phonecall
 */
defined("_VALID_ACCESS") || die();

class CRM_PhoneCallInstall extends ModuleInstall {
	public function install() {
// ************ contacts ************** //
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme('CRM/PhoneCall');
		$fields = array(
			array('name'=>'Subject', 			'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_PhoneCallCommon','display_subject')),

			array('name'=>'Contact Name', 		'type'=>'hidden', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_PhoneCallCommon','display_contact_name')),
			array('name'=>'Phone Number', 		'type'=>'hidden', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_PhoneCallCommon','display_phone_number')),

			array('name'=>'Customer', 			'type'=>'crm_company_contact', 'param'=>array('field_type'=>'select'), 'extra'=>false),
			array('name'=>'Other Customer',		'type'=>'checkbox', 'extra'=>false, 'QFfield_callback'=>array('CRM_PhoneCallCommon','QFfield_other_contact')),
			array('name'=>'Other Customer Name','type'=>'text', 'param'=>'64', 'extra'=>false),

			array('name'=>'Permission', 		'type'=>'commondata', 'required'=>true, 'param'=>array('order_by_key'=>true,'CRM/Access'), 'extra'=>false),
			array('name'=>'Employees', 			'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('CRM_PhoneCallCommon','employees_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>true, 'extra'=>false, 'visible'=>true, 'filter'=>true),

			array('name'=>'Status',				'type'=>'commondata', 'required'=>true, 'filter'=>true, 'param'=>array('order_by_key'=>true,'CRM/Status'), 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_PhoneCallCommon','display_status')),
			array('name'=>'Priority', 			'type'=>'commondata', 'required'=>true, 'param'=>array('order_by_key'=>true,'CRM/Priority'), 'extra'=>false),

			array('name'=>'Phone', 				'type'=>'select', 'extra'=>false, 'QFfield_callback'=>array('CRM_PhoneCallCommon','QFfield_phone'), 'display_callback'=>array('CRM_PhoneCallCommon','display_phone')),
			array('name'=>'Other Phone',		'type'=>'checkbox', 'extra'=>false, 'QFfield_callback'=>array('CRM_PhoneCallCommon','QFfield_other_phone')),
			array('name'=>'Other Phone Number',	'type'=>'text', 'param'=>'64', 'extra'=>false),
			array('name'=>'Date and Time',		'type'=>'timestamp', 'required'=>true, 'extra'=>false, 'visible'=>true),			

			array('name'=>'Description', 		'type'=>'long text', 'required'=>false, 'param'=>'255', 'extra'=>false)
		);
		Utils_RecordBrowserCommon::install_new_recordset('phonecall', $fields);
		Utils_RecordBrowserCommon::set_tpl('phonecall', Base_ThemeCommon::get_template_filename('CRM/PhoneCall', 'default'));
		Utils_RecordBrowserCommon::register_processing_callback('phonecall', array('CRM_PhoneCallCommon', 'submit_phonecall'));
		Utils_RecordBrowserCommon::set_icon('phonecall', Base_ThemeCommon::get_template_filename('CRM/PhoneCall', 'icon.png'));
// 		Utils_RecordBrowserCommon::new_filter('contact', 'Company Name');
//		Utils_RecordBrowserCommon::set_quickjump('contact', 'Last Name');
//		Utils_RecordBrowserCommon::set_favorites('contact', true);
		Utils_RecordBrowserCommon::set_recent('phonecall', 5);
		Utils_RecordBrowserCommon::set_caption('phonecall', 'Phone Calls');
		Utils_RecordBrowserCommon::set_access_callback('phonecall', array('CRM_PhoneCallCommon', 'access_phonecall'));
		Utils_RecordBrowserCommon::enable_watchdog('phonecall', array('CRM_PhoneCallCommon','watchdog_label'));
// ************ addons ************** //
//		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Contacts', 'company_addon', 'Contacts');
//		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Contacts', 'company_attachment_addon', 'Notes');
//		Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Contacts', 'contact_attachment_addon', 'Notes');
		Utils_RecordBrowserCommon::new_addon('phonecall', 'CRM/PhoneCall', 'phonecall_attachment_addon', 'Notes');
		Utils_RecordBrowserCommon::new_addon('phonecall', 'CRM/PhoneCall', 'messanger_addon', 'Alerts');
        CRM_RoundcubeCommon::new_addon('phonecall');
// ************ other ************** //
		Utils_BBCodeCommon::new_bbcode('phone', 'CRM_PhoneCallCommon', 'phone_bbcode');

		$this->add_aco('browse phonecalls',array('Employee'));
		$this->add_aco('view phonecall',array('Employee'));
		$this->add_aco('edit phonecall',array('Employee'));
		$this->add_aco('delete phonecall',array('Employee Manager'));

		$this->add_aco('view protected notes','Employee');
		$this->add_aco('view public notes','Employee');
		$this->add_aco('edit protected notes','Employee Administrator');
		$this->add_aco('edit public notes','Employee');

		if (ModuleManager::is_installed('Premium_SalesOpportunity')>=0)
			Utils_RecordBrowserCommon::new_record_field('phonecall', 'Opportunity', 'select', true, false, 'premium_salesopportunity::Opportunity Name;Premium_SalesOpportunityCommon::crm_opportunity_reference_crits', '', false);

		return true;
	}

	public function uninstall() {
        CRM_RoundcubeCommon::delete_addon('phonecall');
		Base_ThemeCommon::uninstall_default_theme('CRM/PhoneCall');
		Utils_RecordBrowserCommon::delete_addon('phonecall', 'CRM/PhoneCall', 'phonecall_attachment_addon');
		Utils_AttachmentCommon::persistent_mass_delete('phonecall/');
		Utils_RecordBrowserCommon::unregister_processing_callback('phonecall', array('CRM_PhoneCallCommon', 'submit_phonecall'));
		Utils_RecordBrowserCommon::uninstall_recordset('phonecall');
		return true;
	}

	public function requires($v) {
		return array(
			array('name'=>'Utils/RecordBrowser', 'version'=>0),
			array('name'=>'Utils/Attachment', 'version'=>0),
			array('name'=>'CRM/Acl', 'version'=>0),
			array('name'=>'CRM/Contacts', 'version'=>0),
			array('name'=>'CRM/Roundcube', 'version'=>0),
			array('name'=>'CRM/Common', 'version'=>0),
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/Acl', 'version'=>0),
			array('name'=>'Utils/ChainedSelect', 'version'=>0),
			array('name'=>'Data/Countries', 'version'=>0)
		);
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'MIT', 'Description'=>'Module for organising Your contacts.');
	}

	public static function simple_setup() {
		return true;
	}

	public function version() {
		return array('1.0');
	}
}

?>
