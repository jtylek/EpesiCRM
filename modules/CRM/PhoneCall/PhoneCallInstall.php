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
class CRM_PhoneCallInstall extends ModuleInstall {
	public function install() {
// ************ contacts ************** //
		Base_ThemeCommon::install_default_theme('CRM/PhoneCall');
		$fields = array(
			array('name'=>'Subject', 			'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_PhoneCallCommon','display_subject')),
			array('name'=>'Company Name', 		'type'=>'crm_company', 'param'=>array('field_type'=>'select','crits'=>array('CRM_PhoneCallCommon','company_crits')), 'filter'=>true, 'required'=>false, 'extra'=>false, 'visible'=>true),	
			array('name'=>'Contact', 			'type'=>'crm_contact', 'param'=>array('field_type'=>'select','crits'=>array('ChainedSelect','company_name'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'extra'=>false, 'visible'=>true),
			array('name'=>'Other Contact',		'type'=>'checkbox', 'extra'=>false, 'QFfield_callback'=>array('CRM_PhoneCallCommon','QFfield_other_contact')),
			array('name'=>'Other Contact Name',	'type'=>'text', 'param'=>'64', 'extra'=>false),

			array('name'=>'Permission', 		'type'=>'select', 'required'=>true, 'param'=>'__COMMON__::Permissions', 'extra'=>false),
			array('name'=>'Employees', 			'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('CRM_PhoneCallCommon','employees_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>true, 'extra'=>false),

			array('name'=>'Status',				'type'=>'select', 'required'=>true, 'param'=>'__COMMON__::Ticket_Status', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_PhoneCallCommon','display_status')),
			array('name'=>'Priority', 			'type'=>'select', 'required'=>true, 'param'=>'__COMMON__::Priorities', 'extra'=>false),

			array('name'=>'Phone', 				'type'=>'select', 'extra'=>false, 'QFfield_callback'=>array('CRM_PhoneCallCommon','QFfield_phone'), 'display_callback'=>array('CRM_PhoneCallCommon','display_phone')),
			array('name'=>'Other Phone',		'type'=>'checkbox', 'extra'=>false, 'QFfield_callback'=>array('CRM_PhoneCallCommon','QFfield_other_phone')),
			array('name'=>'Other Phone Number',	'type'=>'text', 'param'=>'64', 'extra'=>false),
			array('name'=>'Date and Time',		'type'=>'timestamp', 'required'=>true, 'extra'=>false, 'visible'=>true),

			array('name'=>'Phone Number', 		'type'=>'hidden', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_PhoneCallCommon','display_phone_number')),
			array('name'=>'Contact Name', 		'type'=>'hidden', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_PhoneCallCommon','display_contact_name')),
			
			array('name'=>'Description', 		'type'=>'long text', 'required'=>false, 'param'=>'255', 'extra'=>false)
		);
		Utils_RecordBrowserCommon::install_new_recordset('phonecall', $fields);
		Utils_RecordBrowserCommon::set_tpl('phonecall', Base_ThemeCommon::get_template_filename('CRM/PhoneCall', 'View_entry'));
		Utils_RecordBrowserCommon::set_processing_method('phonecall', array('CRM_PhoneCallCommon', 'submit_phonecall'));
// 		Utils_RecordBrowserCommon::new_filter('contact', 'Company Name');
//		Utils_RecordBrowserCommon::set_quickjump('contact', 'Last Name');
//		Utils_RecordBrowserCommon::set_favorites('contact', true);
		Utils_RecordBrowserCommon::set_recent('phonecall', 5);
		Utils_RecordBrowserCommon::set_caption('phonecall', 'Phone Calls');
//		Utils_RecordBrowserCommon::set_icon('contact', Base_ThemeCommon::get_template_filename('CRM/Contacts', 'icon.png'));
		Utils_RecordBrowserCommon::set_access_callback('phonecall', 'CRM_PhoneCallCommon', 'access_phonecall');
// ************ addons ************** //
//		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Contacts', 'company_addon', 'Contacts');
//		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Contacts', 'company_attachment_addon', 'Notes');
//		Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Contacts', 'contact_attachment_addon', 'Notes');
// ************ other ************** //
		$this->add_aco('browse phonecalls',array('Employee','Customer'));
		$this->add_aco('view phonecall',array('Employee'));
		$this->add_aco('edit phonecall',array('Employee'));
		$this->add_aco('delete phonecall',array('Employee Manager'));

		Utils_CommonDataCommon::new_array('Ticket_Status',array('Open','In Progress','Closed'));
		Utils_CommonDataCommon::new_array('Permissions',array('Public','Protected','Private'));
		Utils_CommonDataCommon::new_array('Priorities',array('Low','Medium','High'));
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/PhoneCall');
//		Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Contacts', 'company_addon');
//		Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Contacts', 'company_attachment_addon');
//		Utils_AttachmentCommon::persistent_mass_delete(null,'CRM/Contact/');
//		Utils_AttachmentCommon::persistent_mass_delete(null,'CRM/Company/');
//		Utils_RecordBrowserCommon::delete_addon('contact', 'CRM/Contacts', 'contact_attachment_addon');
		Utils_RecordBrowserCommon::uninstall_recordset('phonecall');
		Utils_CommonDataCommon::remove('Ticket_Status');
		Utils_CommonDataCommon::remove('Permissions');
		Utils_CommonDataCommon::remove('Priorities');
		return true;
	}

	public function requires($v) {
		return array(
			array('name'=>'Utils/RecordBrowser', 'version'=>0),
			array('name'=>'Utils/Attachment', 'version'=>0),
			array('name'=>'CRM/Acl', 'version'=>0),
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/Acl', 'version'=>0),
			array('name'=>'Utils/ChainedSelect', 'version'=>0),
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
		$loc = Base_RegionalSettingsCommon::get_default_location();
		$count = DB::GetOne('SELECT count(ul.id) FROM user_login ul');
		$ret = array(array('type'=>'text','name'=>'cname','label'=>'Company name','default'=>'','param'=>array('maxlength'=>64),'rule'=>array(array('type'=>'required','message'=>'Field required'))),
			     array('type'=>'text','name'=>'sname','label'=>'Short company name','default'=>'','param'=>array('maxlength'=>64)),
			);
		if($count==1) {
			$ret[] = array('type'=>'text','name'=>'fname','label'=>'Your first name','default'=>'','param'=>array('maxlength'=>64), 'rule'=>array(array('type'=>'required','message'=>'Field required')));
			$ret[] = array('type'=>'text','name'=>'lname','label'=>'Your last name','default'=>'','param'=>array('maxlength'=>64), 'rule'=>array(array('type'=>'required','message'=>'Field required')));
		}
		return array_merge($ret,array(
			     array('type'=>'text','name'=>'address1','label'=>'Address 1','default'=>'','param'=>array('maxlength'=>64)),
			     array('type'=>'text','name'=>'address2','label'=>'Address 2','default'=>'','param'=>array('maxlength'=>64)),
			     array('type'=>'callback','name'=>'country','func'=>array('CRM_ContactsInstall','country_element'),'default'=>$loc['country']),
			     array('type'=>'callback','name'=>'state','func'=>array('CRM_ContactsInstall','state_element'),'default'=>$loc['state']),
			     array('type'=>'text','name'=>'city','label'=>'City','default'=>'','param'=>array('maxlength'=>64), 'rule'=>array(array('type'=>'required','message'=>'Field required'))),
			     array('type'=>'text','name'=>'postal','label'=>'Postal Code','default'=>'','param'=>array('maxlength'=>64)),
			     array('type'=>'text','name'=>'phone','label'=>'Phone','default'=>'','param'=>array('maxlength'=>64)),
			     array('type'=>'text','name'=>'fax','label'=>'Fax','default'=>'','param'=>array('maxlength'=>64)),
			     array('type'=>'text','name'=>'web','label'=>'Web address','default'=>'','param'=>array('maxlength'=>64))
			     ));
	}

	private static $country_elem_name;
	public static function country_element($name, $args, & $def_js) {
		self::$country_elem_name = $name;
		return HTML_QuickForm::createElement('commondata',$name,'Country','Countries');
	}

	public static function state_element($name, $args, & $def_js) {
		return HTML_QuickForm::createElement('commondata',$name,'State',array('Countries',self::$country_elem_name),array('empty_option'=>true));
	}

}

?>
