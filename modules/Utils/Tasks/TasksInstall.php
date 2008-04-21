<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
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
class Utils_TasksInstall extends ModuleInstall {
	public function install() {
// ************ contacts ************** //
		Base_ThemeCommon::install_default_theme('Utils/Tasks');
		$fields = array(
			array('name'=>'Title', 				'type'=>'text', 'required'=>true, 'param'=>'255', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('Utils_TasksCommon','display_title')),

			array('name'=>'Description', 		'type'=>'long text', 'extra'=>false, 'param'=>'255', 'visible'=>false),

			array('name'=>'Employees', 			'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Utils_TasksCommon','employees_crits'), 'format'=>array('Utils_TasksCommon','contact_format_with_balls')), 'display_callback'=>array('Utils_TasksCommon','display_employees'), 'required'=>true, 'extra'=>false, 'visible'=>true),
			array('name'=>'Customers', 			'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Utils_TasksCommon','customers_crits')), 'required'=>true, 'extra'=>false, 'visible'=>true),

			array('name'=>'Status',				'type'=>'select', 'required'=>true, 'visible'=>true, 'filter'=>true, 'param'=>'__COMMON__::Ticket_Status', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('Utils_TasksCommon','display_status')),
			array('name'=>'Priority', 			'type'=>'select', 'required'=>true, 'visible'=>true, 'param'=>'__COMMON__::Priorities', 'extra'=>false),
			array('name'=>'Permission', 		'type'=>'select', 'required'=>true, 'param'=>'__COMMON__::Permissions', 'extra'=>false),

			array('name'=>'Longterm',			'type'=>'checkbox', 'extra'=>false, 'filter'=>true, 'visible'=>true),

			array('name'=>'Is Deadline',		'type'=>'checkbox', 'extra'=>false, 'QFfield_callback'=>array('Utils_TasksCommon','QFfield_is_deadline')),			
			array('name'=>'Deadline',			'type'=>'date', 'extra'=>false, 'visible'=>true),

			array('name'=>'Page id',			'type'=>'hidden', 'extra'=>false)

		);
		Utils_RecordBrowserCommon::install_new_recordset('task', $fields);
		Utils_RecordBrowserCommon::set_tpl('task', Base_ThemeCommon::get_template_filename('Utils/Tasks', 'default'));
		Utils_RecordBrowserCommon::set_processing_method('task', array('Utils_TasksCommon', 'submit_task'));
		Utils_RecordBrowserCommon::set_icon('task', Base_ThemeCommon::get_template_filename('Utils/Tasks', 'icon.png'));
// 		Utils_RecordBrowserCommon::new_filter('contact', 'Company Name');
//		Utils_RecordBrowserCommon::set_quickjump('contact', 'Last Name');
//		Utils_RecordBrowserCommon::set_favorites('contact', true);
		Utils_RecordBrowserCommon::set_recent('task', 5);
		Utils_RecordBrowserCommon::set_caption('task', 'Tasks');
		Utils_RecordBrowserCommon::set_access_callback('task', 'Utils_TasksCommon', 'access_task');
// ************ addons ************** //
		Utils_RecordBrowserCommon::new_addon('task', 'Utils/Tasks', 'task_attachment_addon', 'Notes');
//		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Contacts', 'company_addon', 'Contacts');
//		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Contacts', 'company_attachment_addon', 'Notes');
//		Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Contacts', 'contact_attachment_addon', 'Notes');
// ************ other ************** //
//		$this->add_aco('browse phonecalls',array('Employee','Customer'));
//		$this->add_aco('view phonecall',array('Employee'));
//		$this->add_aco('edit phonecall',array('Employee'));
//		$this->add_aco('delete phonecall',array('Employee Manager'));

		Utils_CommonDataCommon::new_array('Ticket_Status',array('Open','In Progress','Closed'), true); // TODO: move to common module
		Utils_CommonDataCommon::new_array('Permissions',array('Public','Protected','Private'), true);
		Utils_CommonDataCommon::new_array('Priorities',array('Low','Medium','High'), true);

		$ret = DB::CreateTable('task_employees_notified','
			task_id I4 NOTNULL,
			contact_id I4 NOTNULL',
			array('constraints'=>', FOREIGN KEY (task_id) REFERENCES task(ID), FOREIGN KEY (contact_id) REFERENCES contact(ID)'));
		if(!$ret){
			print('Unable to create table \'task_employees_notified\'.<br>');
			return false;
		}

		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/Tasks');
//		Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Contacts', 'company_addon');
//		Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Contacts', 'company_attachment_addon');
//		Utils_AttachmentCommon::persistent_mass_delete(null,'CRM/Contact/');
//		Utils_AttachmentCommon::persistent_mass_delete(null,'CRM/Company/');
//		Utils_RecordBrowserCommon::delete_addon('contact', 'CRM/Contacts', 'contact_attachment_addon');
		DB::DropTable('task_employees_notified');
		Utils_RecordBrowserCommon::uninstall_recordset('task');
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
			array('name'=>'CRM/Contacts', 'version'=>0),
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
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Module for organising Your contacts.');
	}

	public static function simple_setup() {
		return true;
	}

	public function version() {
		return array('0.9');
	}
}

?>
