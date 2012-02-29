<?php
/**
* CRM Contacts class.
 *
 * This class provides initialization data for CRMHR module.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts
 */
defined("_VALID_ACCESS") || die();

class CRM_ContactsInstall extends ModuleInstall {
	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme('CRM/Contacts');
		Utils_RecordBrowserCommon::register_datatype('crm_company', 'CRM_ContactsCommon', 'crm_company_datatype');
		Utils_RecordBrowserCommon::register_datatype('crm_contact', 'CRM_ContactsCommon', 'crm_contact_datatype');
		Utils_RecordBrowserCommon::register_datatype('crm_company_contact', 'CRM_ContactsCommon', 'crm_company_contact_datatype');
		Utils_RecordBrowserCommon::register_datatype('email', 'CRM_ContactsCommon', 'email_datatype');
		ModuleManager::include_common('CRM_Contacts',0);
// ************ companies ************** //
		$fields = array(
			array('name'=>'Company Name',	'type'=>'text', 'required'=>true, 'param'=>'128', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_ContactsCommon', 'display_cname')),
			array('name'=>'Short Name',		'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>false),
			array('name'=>'Phone', 			'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_ContactsCommon', 'display_phone')),
			array('name'=>'Fax', 			'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Email', 			'type'=>'email', 'required'=>false, 'param'=>array('unique'=>true), 'extra'=>false, 'visible'=>false),
			array('name'=>'Web address',	'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'display_callback'=>array('CRM_ContactsCommon', 'display_webaddress'), 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_webaddress')),
			array('name'=>'Group', 			'type'=>'multiselect', 'required'=>false, 'visible'=>true, 'param'=>Utils_RecordBrowserCommon::multiselect_from_common('Companies_Groups'), 'extra'=>false, 'visible'=>true, 'filter'=>true),
			array('name'=>'Permission',		'type'=>'commondata', 'required'=>true, 'param'=>array('order_by_key'=>true,'CRM/Access'), 'extra'=>false),
			array('name'=>'Address 1',		'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'display_callback'=>array('CRM_ContactsCommon','maplink')),
			array('name'=>'Address 2',		'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'display_callback'=>array('CRM_ContactsCommon','maplink')),
			array('name'=>'City',			'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_ContactsCommon','maplink')),
			array('name'=>'Country',		'type'=>'commondata', 'required'=>true, 'param'=>array('Countries'), 'extra'=>false, 'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_country')),
			array('name'=>'Zone',			'type'=>'commondata', 'required'=>false, 'param'=>array('Countries','Country'), 'extra'=>false, 'visible'=>true, 'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_zone')),
			array('name'=>'Postal Code',	'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Tax ID', 		'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false)
		);
		Utils_RecordBrowserCommon::install_new_recordset('company', $fields);
// ************ contacts ************** //
		$fields = array(
			array('name'=>'Last Name', 		'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_ContactsCommon', 'display_lname')),
			array('name'=>'First Name', 	'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_ContactsCommon', 'display_fname')),
			array('name'=>'Company Name', 	'type'=>'crm_company', 'param'=>array('field_type'=>'select'), 'required'=>false, 'extra'=>false, 'visible'=>true, 'filter'=>true),
			array('name'=>'Related Companies', 	'type'=>'crm_company', 'param'=>array('field_type'=>'multiselect'), 'required'=>false, 'extra'=>false, 'visible'=>false, 'filter'=>true),
			array('name'=>'Group', 			'type'=>'multiselect', 'required'=>false, 'param'=>Utils_RecordBrowserCommon::multiselect_from_common('Contacts_Groups'), 'extra'=>false, 'filter'=>true),
			array('name'=>'Title', 			'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Work Phone', 	'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_ContactsCommon', 'display_phone')),
			array('name'=>'Mobile Phone', 	'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_ContactsCommon', 'display_phone')),
			array('name'=>'Fax', 			'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Email', 			'type'=>'email', 'required'=>false, 'param'=>array('unique'=>true), 'extra'=>false, 'visible'=>false),
			array('name'=>'Web address', 	'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'display_callback'=>array('CRM_ContactsCommon', 'display_webaddress'), 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_webaddress')),
			array('name'=>'Address 1', 		'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'display_callback'=>array('CRM_ContactsCommon','maplink')),
			array('name'=>'Address 2', 		'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'display_callback'=>array('CRM_ContactsCommon','maplink')),
			array('name'=>'City', 			'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_ContactsCommon','maplink')),
			array('name'=>'Country', 		'type'=>'commondata', 'required'=>true, 'param'=>array('Countries'), 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_country')),
			array('name'=>'Zone', 			'type'=>'commondata', 'required'=>false, 'param'=>array('Countries','Country'), 'extra'=>false, 'visible'=>true, 'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_zone')),
			array('name'=>'Postal Code', 	'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Permission', 	'type'=>'commondata', 'required'=>true, 'param'=>array('order_by_key'=>true,'CRM/Access'), 'extra'=>false),
			array('name'=>'Home Phone', 	'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>true, 'display_callback'=>array('CRM_ContactsCommon', 'display_phone')),
			array('name'=>'Home Address 1', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>true, 'display_callback'=>array('CRM_ContactsCommon','home_maplink')),
			array('name'=>'Home Address 2', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>true, 'display_callback'=>array('CRM_ContactsCommon','home_maplink')),
			array('name'=>'Home City', 		'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>true, 'display_callback'=>array('CRM_ContactsCommon','home_maplink')),
			array('name'=>'Home Country', 	'type'=>'commondata', 'required'=>false, 'param'=>array('Countries'), 'extra'=>true,'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_country')),
			array('name'=>'Home Zone', 		'type'=>'commondata', 'required'=>false, 'param'=>array('Countries','Home Country'), 'extra'=>true, 'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_zone')),
			array('name'=>'Home Postal Code', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>true),
			array('name'=>'Birth Date', 	'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Login Panel',	'type'=>'page_split', 'param'=>1),
			array('name'=>'Login', 			'type'=>'integer', 'required'=>false, 'param'=>'64', 'extra'=>true, 'display_callback'=>array('CRM_ContactsCommon', 'display_login'), 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_login'), 'style'=>''),
			array('name'=>'Username', 		'type'=>'calculated', 'required'=>false, 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_username')),
			array('name'=>'Set Password', 	'type'=>'calculated', 'required'=>false, 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_password')),
			array('name'=>'Confirm Password','type'=>'calculated', 'required'=>false, 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_repassword')),
			array('name'=>'Admin', 			'type'=>'calculated', 'required'=>false, 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_admin')),
			array('name'=>'Access', 		'type'=>'multiselect', 'required'=>false, 'param'=>Utils_RecordBrowserCommon::multiselect_from_common('Contacts/Access'), 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_access'))
		);
		Utils_RecordBrowserCommon::install_new_recordset('contact', $fields);
        DB::CreateIndex('contact_data_1__f_login_idx','contact_data_1','f_login,active');
// ************ company settings ************** //
		Utils_RecordBrowserCommon::register_processing_callback('company', array('CRM_ContactsCommon', 'submit_company'));
		Utils_RecordBrowserCommon::set_quickjump('company', 'Company Name');
		Utils_RecordBrowserCommon::set_favorites('company', true);
		Utils_RecordBrowserCommon::set_recent('company', 15);
		Utils_RecordBrowserCommon::set_caption('company', 'Companies');
		Utils_RecordBrowserCommon::set_icon('company', Base_ThemeCommon::get_template_filename('CRM/Contacts', 'companies.png'));
		Utils_RecordBrowserCommon::enable_watchdog('company', array('CRM_ContactsCommon','company_watchdog_label'));
        Utils_RecordBrowserCommon::set_clipboard_pattern('company', "%{{company_name}<BR>}\n%{{address_1}<BR>}\n%{{address_2}<BR>}\n%{%{{city} }%{{zone} }{postal_code}<BR>}\n%{{country}<BR>}\n%{tel. {phone}<BR>}\n%{fax. {fax}<BR>}\n%{{web_address}<BR>}");
// ************ contacts settings ************** //
		Utils_RecordBrowserCommon::set_tpl('contact', Base_ThemeCommon::get_template_filename('CRM/Contacts', 'Contact'));
		Utils_RecordBrowserCommon::register_processing_callback('contact', array('CRM_ContactsCommon', 'submit_contact'));
		Utils_RecordBrowserCommon::set_quickjump('contact', 'Last Name');
		Utils_RecordBrowserCommon::set_favorites('contact', true);
		Utils_RecordBrowserCommon::set_recent('contact', 15);
		Utils_RecordBrowserCommon::set_caption('contact', 'Contacts');
		Utils_RecordBrowserCommon::set_icon('contact', Base_ThemeCommon::get_template_filename('CRM/Contacts', 'icon.png'));
		Utils_RecordBrowserCommon::enable_watchdog('contact', array('CRM_ContactsCommon','contact_watchdog_label'));
        Utils_RecordBrowserCommon::set_clipboard_pattern('contact', "%{{first_name} {last_name}<BR>}\n%{{title}<BR>}\n%{{company_name}<BR>}\n%{{address_1}<BR>}\n%{{address_2}<BR>}\n%{%{{city} }%{{zone} }{postal_code}<BR>}\n%{{country}<BR>}\n%{tel. {work_phone}<BR>}\n%{{email}<BR>}");
// ************ addons ************** //
		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Contacts', 'company_addon', 'Contacts');
		Utils_AttachmentCommon::new_addon('company');
		Utils_AttachmentCommon::new_addon('contact');
// ************ other ************** //
		Utils_CommonDataCommon::new_array('Companies_Groups',array('customer'=>'Customer','vendor'=>'Vendor','other'=>'Other','manager'=>'Manager'),true,true);
		Utils_CommonDataCommon::new_array('Contacts_Groups',array('office'=>'Office Staff','field'=>'Field Staff','custm'=>'Customer'),true,true);
		Utils_CommonDataCommon::new_array('Contacts/Access',array('manager'=>'Manager'));
		
		Utils_BBCodeCommon::new_bbcode('contact', 'CRM_ContactsCommon', 'contact_bbcode');
		Utils_BBCodeCommon::new_bbcode('company', 'CRM_ContactsCommon', 'company_bbcode');

		self::install_permissions();

		return true;
	}
	
	public static function install_permissions() {
		Utils_RecordBrowserCommon::wipe_access('company');
		Utils_RecordBrowserCommon::add_access('company', 'view', 'EMPLOYEE', array('(!permission'=>2, '|:Created_by'=>'USER_ID'));
		Utils_RecordBrowserCommon::add_access('company', 'view', 'ALL', array('id'=>'USER_COMPANY'));
		Utils_RecordBrowserCommon::add_access('company', 'add', 'EMPLOYEE');
		Utils_RecordBrowserCommon::add_access('company', 'edit', 'EMPLOYEE', array('(permission'=>0, '|:Created_by'=>'USER_ID'));
		Utils_RecordBrowserCommon::add_access('company', 'edit', array('ALL','ACCESS:manager'), array('id'=>'USER_COMPANY'));
		Utils_RecordBrowserCommon::add_access('company', 'edit', array('EMPLOYEE','ACCESS:manager'), array());
		Utils_RecordBrowserCommon::add_access('company', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
		Utils_RecordBrowserCommon::add_access('company', 'delete', array('EMPLOYEE','ACCESS:manager'));

		Utils_RecordBrowserCommon::wipe_access('contact');
		Utils_RecordBrowserCommon::add_access('contact', 'view', 'EMPLOYEE', array('(!permission'=>2, '|:Created_by'=>'USER_ID'));
		Utils_RecordBrowserCommon::add_access('contact', 'view', 'ALL', array('login'=>'USER_ID'));
		Utils_RecordBrowserCommon::add_access('contact', 'add', 'EMPLOYEE');
		Utils_RecordBrowserCommon::add_access('contact', 'edit', 'EMPLOYEE', array('(permission'=>0, '|:Created_by'=>'USER_ID'), array('access', 'login'));
		Utils_RecordBrowserCommon::add_access('contact', 'edit', 'ALL', array('login'=>'USER_ID'), array('company_name', 'related_companies', 'access', 'login'));
		Utils_RecordBrowserCommon::add_access('contact', 'edit', array('ALL','ACCESS:manager'), array('company_name'=>'USER_COMPANY'), array('login', 'company_name', 'related_companies'));
		Utils_RecordBrowserCommon::add_access('contact', 'edit', array('EMPLOYEE','ACCESS:manager'), array());
		Utils_RecordBrowserCommon::add_access('contact', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
		Utils_RecordBrowserCommon::add_access('contact', 'delete', array('EMPLOYEE','ACCESS:manager'));
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Contacts');
		Utils_RecordBrowserCommon::unregister_datatype('crm_company');
		Utils_RecordBrowserCommon::unregister_datatype('crm_contact');
		Utils_RecordBrowserCommon::unregister_datatype('crm_company_contact');
		Utils_RecordBrowserCommon::unregister_datatype('email');
		Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Contacts', 'company_addon');
		Utils_AttachmentCommon::delete_addon('company');
		Utils_AttachmentCommon::delete_addon('contact');
		Utils_RecordBrowserCommon::uninstall_recordset('company');
		Utils_RecordBrowserCommon::uninstall_recordset('contact');
		Utils_CommonDataCommon::remove('Contacts_Groups');
		Utils_CommonDataCommon::remove('Companies_Groups');
		Utils_RecordBrowserCommon::unregister_processing_callback('contact', array('CRM_ContactsCommon', 'submit_contact'));
		return true;
	}

	public function requires($v) {
		return array(
			array('name'=>'Utils/RecordBrowser', 'version'=>0),
			array('name'=>'Utils/Attachment', 'version'=>0),
			array('name'=>'CRM/Acl', 'version'=>0),
			array('name'=>'CRM/Common', 'version'=>0),
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/Acl', 'version'=>0),
			array('name'=>'Data/Countries', 'version'=>0)
		);
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Module for organising contacts.');
	}

	public static function simple_setup() {
		return 'CRM';
	}

	public function version() {
		return array('0.9');
	}

	public static function post_install() {
		$loc = Base_RegionalSettingsCommon::get_default_location();
		$ret = array(array('type'=>'text','name'=>'cname','label'=>'Company name','default'=>'','param'=>array('maxlength'=>64),'rule'=>array(array('type'=>'required','message'=>'Field required'))),
			     array('type'=>'text','name'=>'sname','label'=>'Short company name','default'=>'','param'=>array('maxlength'=>64)),
			);
		if(Acl::is_user()) {
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

	public static function post_install_process($val) {
		$comp_id = Utils_RecordBrowserCommon::new_record('company',
			array('company_name'=>$val['cname'],
				'short_name'=>isset($val['sname'])?$val['sname']:'',
				'address_1'=>isset($val['address1'])?$val['address1']:'',
				'address_2'=>isset($val['address2'])?$val['address2']:'',
				'country'=>isset($val['country'])?$val['country']:'',
				'zone'=>isset($val['state'])?$val['state']:'',
				'city'=>isset($val['city'])?$val['city']:'',
				'postal_code'=>isset($val['postal'])?$val['postal']:'',
				'phone'=>isset($val['phone'])?$val['phone']:'',
				'fax'=>isset($val['fax'])?$val['fax']:'',
				'permission'=>'0',
				'web_address'=>isset($val['web'])?$val['web']:'',
				'group'=>array('other')
				));
		Variable::set('main_company',$comp_id);
		if(Acl::is_user()) {
			$mail = DB::GetOne('SELECT up.mail FROM user_password up WHERE up.user_login_id=%d',array(Acl::get_user()));

			Utils_RecordBrowserCommon::new_record('contact',
				array('first_name'=>$val['fname'],
					'last_name'=>$val['lname'],
					'address_1'=>isset($val['address1'])?$val['address1']:'',
					'address_2'=>isset($val['address2'])?$val['address2']:'',
					'country'=>isset($val['country'])?$val['country']:'',
					'zone'=>isset($val['state'])?$val['state']:'',
					'city'=>isset($val['city'])?$val['city']:'',
					'postal_code'=>isset($val['postal'])?$val['postal']:'',
					'work_phone'=>isset($val['phone'])?$val['phone']:'',
					'fax'=>isset($val['fax'])?$val['fax']:'',
					'web_address'=>isset($val['web'])?$val['web']:'',
					'company_name'=>$comp_id,
					'login'=>Acl::get_user(),
					'permission'=>'0',
					'email'=>$mail,
					'group'=>array('office','field')
					));
		}
	}
}

?>
