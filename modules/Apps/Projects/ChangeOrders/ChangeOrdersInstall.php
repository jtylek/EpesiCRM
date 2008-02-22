<?php
/**
 * Projects Manager - Change Orders
 * @author jtylek@telaxus.com
 * @copyright jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-projects
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Projects_ChangeOrdersInstall extends ModuleInstall {

	public function install() {
		
		$fields = array(
			array('name'=>'CO Number', 'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('Apps_Projects_ChangeOrdersCommon', 'changeorder_callback')),
			array('name'=>'ZSI Estimator','type'=>'crm_contact', 'param'=>array('field_type'=>'select', 'crits'=>array('Apps_ProjectsCommon','projects_employees_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>true, 'extra'=>false, 'visible'=>true),
			// Project should be select
			array('name'=>'Project Name', 'type'=>'select','param'=>array('projects'=>'Project Name'), 'required'=>true, 'extra'=>false, 'visible'=>true, 'display_callback'=>array('Apps_Projects_ChangeOrdersCommon', 'proj_name_callback')),
			// Default date needed to be set to today.
			array('name'=>'Date', 'type'=>'date', 'required'=>true, 'param'=>64, 'extra'=>false, 'visible'=>false),
			array('name'=>'CO Type', 'type'=>'commondata', 'required'=>true, 'visible'=>true, 'param'=>'ChangeOrder_Type', 'extra'=>false),
			array('name'=>'CO Job Type', 'type'=>'commondata', 'required'=>true, 'visible'=>false, 'param'=>'ChangeOrder_JobType', 'extra'=>false),
			array('name'=>'Description', 'type'=>'long text', 'required'=>false, 'param'=>'254', 'extra'=>false),
			array('name'=>'GC CO No','type'=>'text', 'required'=>false, 'param'=>'64','extra'=>false, 'visible'=>true),
			array('name'=>'Est Labor', 'type'=>'currency', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>false),
			array('name'=>'Est Material', 'type'=>'currency', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>false),
			array('name'=>'Est Mandays', 'type'=>'integer', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>false),	
			array('name'=>'Approved', 'type'=>'checkbox', 'required'=>false, 'extra'=>false, 'visible'=>false),
			array('name'=>'Approved Date', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>false, 'visible'=>true),
			array('name'=>'Billed', 'type'=>'checkbox', 'required'=>false, 'extra'=>false, 'visible'=>false),
			array('name'=>'Billed Date', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>false, 'visible'=>false)
		);
		Utils_RecordBrowserCommon::install_new_recordset('changeorders', $fields);
		//Utils_RecordBrowserCommon::set_tpl('contact', Base_ThemeCommon::get_template_filename('CRM/Contacts', 'Contact'));
		//Utils_RecordBrowserCommon::set_quickjump('changeorders', 'Change Order');
		Utils_RecordBrowserCommon::set_recent('changeorders', 15);
		Utils_RecordBrowserCommon::set_caption('changeorders', 'Change Orders');
		Utils_RecordBrowserCommon::set_icon('changeorders', Base_ThemeCommon::get_template_filename('Apps/Projects', 'icon.png'));
//		Utils_RecordBrowserCommon::set_access_callback('changeorders', 'Apps_ProjectsCommon', 'access_projects');
		
// ************ addons ************** //
//	Parameters: ('table','ModuleLocation','function','Label');
		Utils_RecordBrowserCommon::new_addon('projects', 'Apps/Projects/ChangeOrders', 'project_changeorders_addon', 'Change Orders');
//
// ************ other ************** //	
		Utils_CommonDataCommon::new_array('ChangeOrder_Type',array('CO','COP'));
		Utils_CommonDataCommon::new_array('ChangeOrder_JobType',array('wc'=>'WC','paint'=>'Painting','acoust'=>'Acoustic'));
		
		Utils_RecordBrowserCommon::set_favorites('changeorders', true);

		return true;
	}
	
	public function uninstall() {
		//Base_ThemeCommon::uninstall_default_theme('Apps/Projects');
		Utils_RecordBrowserCommon::delete_addon('projects', 'Apps/Projects/ChangeOrders', 'project_changeorders_addon');
		Utils_RecordBrowserCommon::uninstall_recordset('changeorders');
		Utils_CommonDataCommon::remove('ChangeOrder_Type');
		Utils_CommonDataCommon::remove('ChangeOrder_JobType');
		//Utils_AttachmentCommon::persistent_mass_delete(null,'Apps/Projects/ChangeOrders');
		return true;
	}
	
	public function version() {
		return array("0.2");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base','version'=>0),
			array('name'=>'CRM/Contacts','version'=>0),
			array('name'=>'Apps/Projects','version'=>0)
			);
	}
	
	public static function info() {
		return array(
			'Description'=>'Projects Manager for ZSI Painting - Change Orders',
			'Author'=>'jtylek@telaxus.com',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>