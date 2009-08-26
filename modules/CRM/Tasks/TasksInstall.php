<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage tasks
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_TasksInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme('CRM/Tasks');
		$fields = array(
			array('name'=>'Title', 				'type'=>'text', 'required'=>true, 'param'=>'255', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_TasksCommon','display_title')),

			array('name'=>'Description', 		'type'=>'long text', 'extra'=>false, 'param'=>'255', 'visible'=>false),

			array('name'=>'Employees', 			'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('CRM_TasksCommon','employees_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'display_callback'=>array('CRM_TasksCommon','display_employees'), 'required'=>true, 'extra'=>false, 'visible'=>true),
			array('name'=>'Customers', 			'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('CRM_TasksCommon','customers_crits')), 'extra'=>false, 'visible'=>true),

			array('name'=>'Status',				'type'=>'commondata', 'required'=>true, 'visible'=>true, 'filter'=>true, 'param'=>array('order_by_key'=>true,'CRM/Status'), 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_TasksCommon','display_status')),
			array('name'=>'Priority', 			'type'=>'commondata', 'required'=>true, 'visible'=>true, 'param'=>array('order_by_key'=>true,'CRM/Priority'), 'extra'=>false),
			array('name'=>'Permission', 		'type'=>'commondata', 'required'=>true, 'param'=>array('order_by_key'=>true,'CRM/Access'), 'extra'=>false),

			array('name'=>'Longterm',			'type'=>'checkbox', 'extra'=>false, 'filter'=>true, 'visible'=>true),

//			array('name'=>'Is Deadline',		'type'=>'checkbox', 'extra'=>false, 'QFfield_callback'=>array('CRM_TasksCommon','QFfield_is_deadline')),			
			array('name'=>'Deadline',			'type'=>'date', 'extra'=>false, 'visible'=>true)
		);
		Utils_RecordBrowserCommon::install_new_recordset('task', $fields);
		Utils_RecordBrowserCommon::set_tpl('task', Base_ThemeCommon::get_template_filename('CRM/Tasks', 'default'));
		Utils_RecordBrowserCommon::set_processing_callback('task', array('CRM_TasksCommon', 'submit_task'));
		Utils_RecordBrowserCommon::set_icon('task', Base_ThemeCommon::get_template_filename('CRM/Tasks', 'icon.png'));
// 		Utils_RecordBrowserCommon::new_filter('contact', 'Company Name');
//		Utils_RecordBrowserCommon::set_quickjump('contact', 'Last Name');
//		Utils_RecordBrowserCommon::set_favorites('contact', true);
		Utils_RecordBrowserCommon::set_recent('task', 5);
		Utils_RecordBrowserCommon::set_caption('task', 'Tasks');
		Utils_RecordBrowserCommon::set_access_callback('task', array('CRM_TasksCommon', 'access_task'));
		Utils_RecordBrowserCommon::enable_watchdog('task', array('CRM_TasksCommon','watchdog_label'));
// ************ addons ************** //
		Utils_RecordBrowserCommon::new_addon('task', 'CRM/Tasks', 'task_attachment_addon', 'Notes');
//		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Contacts', 'company_addon', 'Contacts');
//		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Contacts', 'company_attachment_addon', 'Notes');
//		Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Contacts', 'contact_attachment_addon', 'Notes');
// ************ other ************** //
		Utils_BBCodeCommon::new_bbcode('task', 'CRM_TasksCommon', 'task_bbcode');

		$this->add_aco('browse tasks',array('Employee'));
		$this->add_aco('view task',array('Employee'));
		$this->add_aco('edit task',array('Employee'));
		$this->add_aco('delete task',array('Employee Manager'));

		$this->add_aco('view protected notes','Employee');
		$this->add_aco('view public notes','Employee');
		$this->add_aco('edit protected notes','Employee Administrator');
		$this->add_aco('edit public notes','Employee');
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Tasks');
		Utils_RecordBrowserCommon::uninstall_recordset('task');
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
			array('name'=>'CRM/MailClient', 'version'=>0),
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/Acl', 'version'=>0),
			array('name'=>'Utils/ChainedSelect', 'version'=>0),
			array('name'=>'Data/Countries', 'version'=>0),
			array('name'=>'CRM/Filters','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Base/Theme','version'=>0));
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Module for organising todo list.');
	}

	public static function simple_setup() {
		return true;
	}

	public static function backup() {
		return Utils_RecordBrowserCommon::get_tables('task');		
	}
}

?>
