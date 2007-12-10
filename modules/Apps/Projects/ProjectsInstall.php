<?php
/**
 * Projects Manager
 * @author jtylek@telaxus.com
 * @copyright jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-projects
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ProjectsInstall extends ModuleInstall {

	public function install() {
		
		// Base_ThemeCommon::install_default_theme('Apps/Projects');
		$fields = array(
			array('name'=>'Project Name', 'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true,'display_callback'=>array('Apps_ProjectsCommon', 'display_proj_name')),	
			array('name'=>'Company Name', 'type'=>'select', 'required'=>true, 'param'=>array('company'=>'Company Name'), 'extra'=>false, 'visible'=>true),
			array('name'=>'Reference No', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>true),
			
			array('name'=>'Address 1', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Address 2', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'City', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>true),
			array('name'=>'Country', 'type'=>'commondata', 'required'=>true, 'param'=>array('Countries'), 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_country')),
			array('name'=>'Zone', 'type'=>'commondata', 'required'=>false, 'param'=>array('Countries','Country'), 'extra'=>false, 'visible'=>true, 'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_zone')),
			array('name'=>'Postal Code', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Status', 'type'=>'commondata', 'required'=>true, 'param'=>'Project_Status', 'extra'=>false),
			array('name'=>'Description', 'type'=>'long text', 'required'=>false, 'param'=>'64', 'extra'=>false)
		);
		Utils_RecordBrowserCommon::install_new_recordset('projects', $fields);
		//Utils_RecordBrowserCommon::set_tpl('contact', Base_ThemeCommon::get_template_filename('CRM/Contacts', 'Contact'));
		//Utils_RecordBrowserCommon::set_processing_method('projects', array('Apps_ProjectsCommon', 'submit_project'));
		Utils_RecordBrowserCommon::new_filter('projects', 'Company Name');
		Utils_RecordBrowserCommon::set_quickjump('projects', 'Project Name');
		Utils_RecordBrowserCommon::set_favorites('projects', true);
		Utils_RecordBrowserCommon::set_recent('projects', 15);
		Utils_RecordBrowserCommon::set_caption('projects', 'Projects');
		Utils_RecordBrowserCommon::set_icon('projects', Base_ThemeCommon::get_template_file('Apps/Projects', 'icon.png'));
		Utils_RecordBrowserCommon::set_access_callback('projects', 'Apps_ProjectsCommon', 'access_projects');
		
// ************ addons ************** //
		Utils_RecordBrowserCommon::new_addon('projects', 'Apps/Projects', 'project_attachment_addon', 'Notes');

// ************ other ************** //	
		Utils_CommonDataCommon::new_array('Project_Status',array('New','Submitted','Awarded','Canceled','In Progress','Completed'));
		
		$this->add_aco('browse projects',array('Employee'));
		$this->add_aco('view projects',array('Employee'));
		$this->add_aco('edit projects',array('Employee'));
		$this->add_aco('delete projects',array('Employee Manager'));

		$this->add_aco('view deleted notes','Employee Manager');
		$this->add_aco('view protected notes','Employee');
		$this->add_aco('view public notes','Employee');
		$this->add_aco('edit protected notes','Employee Administrator');
		$this->add_aco('edit public notes','Employee');
		
		return true;
	}
	
	public function uninstall() {
		//Base_ThemeCommon::uninstall_default_theme('Apps/Projects');
		Utils_RecordBrowserCommon::delete_addon('projects', 'Apps/Projects', 'project_attachment_addon');
		Utils_RecordBrowserCommon::uninstall_recordset('projects');
		Utils_CommonDataCommon::remove('Project_Status');
		return true;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base','version'=>0),
			array('name'=>'CRM/Contacts','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Projects Manager',
			'Author'=>'jtylek@telaxus.com',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>