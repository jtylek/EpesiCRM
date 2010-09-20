<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage meetings
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_MeetingInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme('CRM/Meeting');
		$fields = array(
			array('name'=>'Title', 				'type'=>'text', 'required'=>true, 'param'=>'255', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_MeetingCommon','display_title')),

			array('name'=>'Description', 		'type'=>'long text', 'extra'=>false, 'param'=>'255', 'visible'=>false),

			array('name'=>'Date', 				'type'=>'date', 'extra'=>false, 'visible'=>true, 'param'=>'255'),
			array('name'=>'Time', 				'type'=>'time', 'extra'=>false, 'visible'=>true, 'param'=>'255'),
			array('name'=>'Duration', 			'type'=>'integer', 'extra'=>false, 'param'=>'255', 'visible'=>false, 'QFfield_callback'=>array('CRM_MeetingCommon','QFfield_duration')),

			array('name'=>'Employees', 			'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('CRM_MeetingCommon','employees_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'display_callback'=>array('CRM_MeetingCommon','display_employees'), 'required'=>true, 'extra'=>false, 'visible'=>true),
			array('name'=>'Customers', 			'type'=>'crm_company_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('CRM_MeetingCommon','customers_crits')), 'extra'=>false, 'visible'=>true),

			array('name'=>'Status',				'type'=>'commondata', 'required'=>true, 'visible'=>true, 'filter'=>true, 'param'=>array('order_by_key'=>true,'CRM/Status'), 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_MeetingCommon','display_status')),
			array('name'=>'Priority', 			'type'=>'commondata', 'required'=>true, 'visible'=>true, 'param'=>array('order_by_key'=>true,'CRM/Priority'), 'extra'=>false),
			array('name'=>'Permission', 		'type'=>'commondata', 'required'=>true, 'param'=>array('order_by_key'=>true,'CRM/Access'), 'extra'=>false),

			array('name'=>'Recurrence type',	'type'=>'integer', 'required'=>false, 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_MeetingCommon','QFfield_recurrence')),
			array('name'=>'Recurrence end', 	'type'=>'date', 'required'=>false, 'extra'=>false, 'QFfield_callback'=>array('CRM_MeetingCommon','QFfield_recurrence_end'),'visible'=>false),
			array('name'=>'Recurrence hash', 	'type'=>'text', 'required'=>false, 'param'=>'16', 'extra'=>false, 'QFfield_callback'=>array('CRM_MeetingCommon','QFfield_recurrence_hash'),'visible'=>false)
		);
		Utils_RecordBrowserCommon::install_new_recordset('crm_meeting', $fields);
		Utils_RecordBrowserCommon::set_tpl('crm_meeting', Base_ThemeCommon::get_template_filename('CRM/Meeting', 'default'));
		Utils_RecordBrowserCommon::register_processing_callback('crm_meeting', array('CRM_MeetingCommon', 'submit_meeting'));
		Utils_RecordBrowserCommon::set_icon('crm_meeting', Base_ThemeCommon::get_template_filename('CRM/Meeting', 'icon.png'));
		Utils_RecordBrowserCommon::set_recent('crm_meeting', 10);
		Utils_RecordBrowserCommon::set_caption('crm_meeting', 'Meetings');
		Utils_RecordBrowserCommon::set_access_callback('crm_meeting', array('CRM_MeetingCommon', 'access_meeting'));
		Utils_RecordBrowserCommon::enable_watchdog('crm_meeting', array('CRM_MeetingCommon','watchdog_label'));
// ************ addons ************** //
		Utils_RecordBrowserCommon::new_addon('crm_meeting', 'CRM/Meeting', 'meeting_attachment_addon', 'Notes');
		Utils_RecordBrowserCommon::new_addon('crm_meeting', 'CRM/Meeting', 'messanger_addon', 'Alerts');
// ************ other ************** //
		CRM_CalendarCommon::new_event_handler('Meetings', array('CRM_MeetingCommon', 'crm_calendar_handler'));
        CRM_RoundcubeCommon::new_addon('crm_meeting');

		Utils_BBCodeCommon::new_bbcode('meeting', 'CRM_MeetingCommon', 'meeting_bbcode');

		$this->add_aco('browse meetings',array('Employee'));
		$this->add_aco('view meeting',array('Employee'));
		$this->add_aco('edit meeting',array('Employee'));
		$this->add_aco('delete meeting',array('Employee Manager'));

		$this->add_aco('view protected notes','Employee');
		$this->add_aco('view public notes','Employee');
		$this->add_aco('edit protected notes','Employee Administrator');
		$this->add_aco('edit public notes','Employee');
		return true;
	}

	public function uninstall() {
        CRM_RoundcubeCommon::delete_addon('crm_meeting');
		CRM_CalendarCommon::delete_event_handler('Meetings');
		Base_ThemeCommon::uninstall_default_theme('CRM/Meeting');
		Utils_RecordBrowserCommon::uninstall_recordset('crm_meeting');
		Utils_RecordBrowserCommon::unregister_processing_callback('crm_meeting', array('CRM_MeetingCommon', 'submit_meeting'));
		return true;
	}

	public function version() {
		return array("1.0");
	}

	public function requires($v) {
		return array(
			array('name'=>'Utils/RecordBrowser', 'version'=>0),
			array('name'=>'Utils/Attachment', 'version'=>0),
			array('name'=>'CRM/Common', 'version'=>0),
			array('name'=>'CRM/Acl', 'version'=>0),
			array('name'=>'CRM/Contacts', 'version'=>0),
			array('name'=>'CRM/Roundcube', 'version'=>0),
			array('name'=>'CRM/Calendar', 'version'=>0),
			array('name'=>'CRM/Followup', 'version'=>0),
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/Acl', 'version'=>0),
			array('name'=>'Utils/ChainedSelect', 'version'=>0),
			array('name'=>'Data/Countries', 'version'=>0),
			array('name'=>'CRM/Filters','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Base/Theme','version'=>0));
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Meeting schedule module.');
	}

	public static function simple_setup() {
		return true;
	}
}

?>
