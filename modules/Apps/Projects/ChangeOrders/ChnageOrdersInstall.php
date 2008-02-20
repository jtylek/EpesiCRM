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

class Apps_ChangeOrders_Install extends ModuleInstall {

	public function install() {
		
		$fields = array(
			array('name'=>'Project', 'type'=>'select', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true,'display_callback'=>array('Apps_ProjectsCommon', 'display_proj_name')),
			array('name'=>'Date', 'type'=>'date', 'required'=>true, 'param'=>64, 'extra'=>false, 'visible'=>true),
			array('name'=>'Type', 'type'=>'commondata', 'required'=>true, 'visible'=>true, 'param'=>'ChangeOrder_Type', 'extra'=>false),
			array('name'=>'Description', 'type'=>'long text', 'required'=>false, 'param'=>'254', 'extra'=>false),
			array('name'=>'Labor', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>false),
			array('name'=>'Material', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>false),
			array('name'=>'GC CO No','type'=>'text', 'required'=>true, 'param'=>'64','extra'=>false, 'visible'=>true),
			array('name'=>'Approved', 'type'=>'checkbox', 'required'=>false, 'extra'=>false, 'visible'=>true),
			array('name'=>'Approved Date', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>false),
			array('name'=>'Billed', 'type'=>'checkbox', 'required'=>false, 'extra'=>false, 'visible'=>true),
			array('name'=>'Billed Date', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>false)
		);
		Utils_RecordBrowserCommon::install_new_recordset('changeorders', $fields);
		//Utils_RecordBrowserCommon::set_tpl('contact', Base_ThemeCommon::get_template_filename('CRM/Contacts', 'Contact'));
		//Utils_RecordBrowserCommon::set_processing_method('projects', array('Apps_ProjectsCommon', 'submit_project'));
		Utils_RecordBrowserCommon::new_filter('changeorders', 'Project');
		Utils_RecordBrowserCommon::set_quickjump('changeorders', 'Change Order');
		Utils_RecordBrowserCommon::set_recent('changeorders', 15);
		Utils_RecordBrowserCommon::set_caption('changeorders', 'Change Orders');
		Utils_RecordBrowserCommon::set_icon('changeorders', Base_ThemeCommon::get_template_filename('Apps/Projects', 'icon.png'));
//		Utils_RecordBrowserCommon::set_access_callback('changeorders', 'Apps_ProjectsCommon', 'access_projects');
		
// ************ addons ************** //
		Utils_RecordBrowserCommon::new_addon('projects', 'Apps/Projects', 'project_changeorders_addon', 'Change Orders');
//		Utils_RecordBrowserCommon::new_addon('company', 'Apps/Projects', 'company_projects_addon', 'Projects');

// ************ other ************** //	
		Utils_CommonDataCommon::new_array('ChangeOrder_Type',array('CO','COP'));
		
/*
		$this->add_aco('browse projects',array('Employee'));
		$this->add_aco('view projects',array('Employee'));
		$this->add_aco('edit projects',array('Employee'));
		$this->add_aco('delete projects',array('Employee Manager'));

		$this->add_aco('view deleted notes','Employee Manager');
		$this->add_aco('view protected notes','Employee');
		$this->add_aco('view public notes','Employee');
		$this->add_aco('edit protected notes','Employee Administrator');
		$this->add_aco('edit public notes','Employee');
*/		
		return true;
	}
	
	public function uninstall() {
		//Base_ThemeCommon::uninstall_default_theme('Apps/Projects');
		Utils_RecordBrowserCommon::delete_addon('projects', 'Apps/Projects', 'project_attachment_addon');
		Utils_RecordBrowserCommon::uninstall_recordset('projects');
		Utils_CommonDataCommon::remove('Project_Status');
		Utils_AttachmentCommon::persistent_mass_delete(null,'Apps/Projects/');
		return true;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base','version'=>0),
			array('name'=>'CRM/Contacts','version'=>0));
			array('name'=>'Apps/Projects','version'=>0));
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