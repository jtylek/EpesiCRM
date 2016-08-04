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
        //addons table
        $fields = array(
            array(
                'name'  => _M('Recordset'),
                'type'  => 'text',
                'param' => 64,
                'display_callback' => array(
                    $this->get_type() . 'Common',
                    'display_recordset',
                ),
                'QFfield_callback' => array(
                    $this->get_type() . 'Common',
                    'QFfield_recordset',
                ),
                'required' => true,
                'extra'    => false,
                'visible'  => true,
            ),
        );
        Utils_RecordBrowserCommon::install_new_recordset('phonecall_related', $fields);
        Utils_RecordBrowserCommon::set_caption('phonecall_related', _M('Phonecalls Related Recordsets'));
        Utils_RecordBrowserCommon::register_processing_callback('phonecall_related', array('CRM_PhoneCallCommon', 'processing_related'));
        Utils_RecordBrowserCommon::add_access('phonecall_related', 'view', 'ACCESS:employee');
        Utils_RecordBrowserCommon::add_access('phonecall_related', 'add', 'ADMIN');
        Utils_RecordBrowserCommon::add_access('phonecall_related', 'edit', 'SUPERADMIN');
        Utils_RecordBrowserCommon::add_access('phonecall_related', 'delete', 'SUPERADMIN');
// ************ phone calls ************** //
		Base_ThemeCommon::install_default_theme(CRM_PhoneCallInstall::module_name());
		$fields = array(
			array('name' => _M('Subject'), 			'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_PhoneCallCommon','display_subject')),

			array('name' => _M('Contact Name'), 		'type'=>'hidden', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_PhoneCallCommon','display_contact_name')),
			array('name' => _M('Phone Number'), 		'type'=>'hidden', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_PhoneCallCommon','display_phone_number')),

			array('name' => _M('Customer'), 			'type'=>'crm_company_contact', 'param'=>array('field_type'=>'select'), 'extra'=>false),
			array('name' => _M('Other Customer'),		'type'=>'checkbox', 'extra'=>false),
			array('name' => _M('Other Customer Name'),'type'=>'text', 'param'=>'64', 'extra'=>false),

			array('name' => _M('Permission'), 		'type'=>'commondata', 'required'=>true, 'param'=>array('order_by_key'=>true,'CRM/Access'), 'extra'=>false),
			array('name' => _M('Employees'), 			'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('CRM_PhoneCallCommon','employees_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>true, 'extra'=>false, 'visible'=>true, 'filter'=>true),

			array('name' => _M('Status'),				'type'=>'commondata', 'required'=>true, 'filter'=>true, 'param'=>array('order_by_key'=>true,'CRM/Status'), 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_PhoneCallCommon','display_status')),
			array('name' => _M('Priority'), 			'type'=>'commondata', 'required'=>true, 'param'=>array('order_by_key'=>true,'CRM/Priority'), 'extra'=>false),

			array('name' => _M('Phone'), 				'type'=>'integer', 'extra'=>false, 'QFfield_callback'=>array('CRM_PhoneCallCommon','QFfield_phone'), 'display_callback'=>array('CRM_PhoneCallCommon','display_phone')),
			array('name' => _M('Other Phone'),		'type'=>'checkbox', 'extra'=>false),
			array('name' => _M('Other Phone Number'),	'type'=>'text', 'param'=>'64', 'extra'=>false),
			array('name' => _M('Date and Time'),		'type'=>'timestamp', 'required'=>true, 'extra'=>false, 'visible'=>true),			

			array('name' => _M('Description'), 		'type'=>'long text', 'required'=>false, 'param'=>'255', 'extra'=>false),
            array(
                'name'     => _M('Related'),
                'type'     => 'multiselect',
                'QFfield_callback' => array(
                    'CRM_PhoneCallCommon',
                    'QFfield_related',
                ),
                'param'    => '__RECORDSETS__::;CRM_PhoneCallCommon::related_crits',
                'extra'    => false,
                'required' => false,
                'visible'  => true,
            ),
		);
		Utils_RecordBrowserCommon::install_new_recordset('phonecall', $fields);
		Utils_RecordBrowserCommon::set_tpl('phonecall', Base_ThemeCommon::get_template_filename(CRM_PhoneCallInstall::module_name(), 'default'));
		Utils_RecordBrowserCommon::register_processing_callback('phonecall', array('CRM_PhoneCallCommon', 'submit_phonecall'));
		Utils_RecordBrowserCommon::set_icon('phonecall', Base_ThemeCommon::get_template_filename(CRM_PhoneCallInstall::module_name(), 'icon.png'));
		Utils_RecordBrowserCommon::set_recent('phonecall', 5);
		Utils_RecordBrowserCommon::set_caption('phonecall', _M('Phonecalls'));
		Utils_RecordBrowserCommon::enable_watchdog('phonecall', array('CRM_PhoneCallCommon','watchdog_label'));
        Utils_RecordBrowserCommon::set_search('phonecall',2,0);

// ************ addons ************** //
		Utils_AttachmentCommon::new_addon('phonecall');
		Utils_RecordBrowserCommon::new_addon('phonecall', CRM_PhoneCallInstall::module_name(), 'messanger_addon', _M('Alerts'));
        CRM_MailCommon::new_addon('phonecall');
// ************ other ************** //
		CRM_CalendarCommon::new_event_handler(_M('Phonecalls'), array('CRM_PhoneCallCommon', 'crm_calendar_handler'));
		Utils_BBCodeCommon::new_bbcode('phone', 'CRM_PhoneCallCommon', 'phone_bbcode');

		if (ModuleManager::is_installed('Premium_SalesOpportunity')>=0)
			Utils_RecordBrowserCommon::new_record_field('phonecall', _M('Opportunity'), 'select', true, false, 'premium_salesopportunity::Opportunity Name;Premium_SalesOpportunityCommon::crm_opportunity_reference_crits', '', false);

		Utils_RecordBrowserCommon::add_access('phonecall', 'view', 'ACCESS:employee', array('(!permission'=>2, '|employees'=>'USER'));
		Utils_RecordBrowserCommon::add_access('phonecall', 'add', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('phonecall', 'edit', 'ACCESS:employee', array('(permission'=>0, '|employees'=>'USER', '|customer'=>'USER'));
		Utils_RecordBrowserCommon::add_access('phonecall', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
		Utils_RecordBrowserCommon::add_access('phonecall', 'delete', array('ACCESS:employee','ACCESS:manager'));

		return true;
	}

	public function uninstall() {
		CRM_CalendarCommon::delete_event_handler('Phonecalls');
        CRM_MailCommon::delete_addon('phonecall');
		Base_ThemeCommon::uninstall_default_theme(CRM_PhoneCallInstall::module_name());
		Utils_AttachmentCommon::delete_addon('phonecall');
		Utils_AttachmentCommon::persistent_mass_delete('phonecall/');
		Utils_RecordBrowserCommon::unregister_processing_callback('phonecall', array('CRM_PhoneCallCommon', 'submit_phonecall'));
		Utils_RecordBrowserCommon::uninstall_recordset('phonecall');
		return true;
	}

	public function requires($v) {
		return array(
			array('name'=>Utils_RecordBrowserInstall::module_name(), 'version'=>0),
			array('name'=>Utils_AttachmentInstall::module_name(), 'version'=>0),
			array('name'=>CRM_ContactsInstall::module_name(), 'version'=>0),
			array('name'=>CRM_RoundcubeInstall::module_name(), 'version'=>0),
			array('name'=>CRM_CommonInstall::module_name(), 'version'=>0),
			array('name'=>CRM_CalendarInstall::module_name(), 'version'=>0),
			array('name'=>Base_LangInstall::module_name(), 'version'=>0),
			array('name'=>Base_AclInstall::module_name(), 'version'=>0),
			array('name'=>Utils_ChainedSelectInstall::module_name(), 'version'=>0),
			array('name'=>Data_CountriesInstall::module_name(), 'version'=>0)
		);
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'MIT', 'Description'=>'Module for organising Your contacts.');
	}

	public static function simple_setup() {
		return 'CRM';
	}

	public function version() {
		return array('1.0');
	}
}

?>
