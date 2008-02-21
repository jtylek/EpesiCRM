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
			array('name'=>'Company Name', 'type'=>'crm_company', 'param'=>array('field_type'=>'select','crits'=>array('Apps_ProjectsCommon','projects_company_crits')), 'filter'=>true, 'required'=>true, 'extra'=>false, 'visible'=>true),	
			array('name'=>'Project Address 1', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Project Address 2', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'City', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>false),
			array('name'=>'Country', 'type'=>'commondata', 'required'=>true, 'param'=>array('Countries'), 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_country')),
			array('name'=>'State', 'type'=>'commondata', 'required'=>false, 'param'=>array('Countries','Country'), 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_zone')),
			array('name'=>'Postal Code', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Job Type', 'type'=>'commondata', 'required'=>true, 'visible'=>false, 'param'=>'Job_Type', 'extra'=>false),
			array('name'=>'TIM Job No', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>true),
			array('name'=>'Contract Amount', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>false, 'visible'=>false),
			array('name'=>'Status', 'type'=>'commondata', 'required'=>true, 'visible'=>true, 'filter'=>true, 'param'=>'Project_Status', 'extra'=>false),
			array('name'=>'Change Order', 'type'=>'calculated', 'visible'=>true, 'extra'=>false, 'display_callback'=>array('Apps_ProjectsCommon', 'display_change_order')),
			
			//array('name'=>'ZSI Estimator','type'=>'crm_contact', 'param'=>array('field_type'=>'select', 'crits'=>array('Apps_ProjectsCommon','projects_employees_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>false),
			array('name'=>'ZSI Estimator','type'=>'crm_contact', 'param'=>array('field_type'=>'select', 'crits'=>array('Apps_ProjectsCommon','projects_employees_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>false),	
			array('name'=>'ZSI Project Manager','type'=>'crm_contact', 'param'=>array('field_type'=>'select', 'crits'=>array('Apps_ProjectsCommon','projects_employees_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>false),
		
			array('name'=>'Start Date', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>false),
			array('name'=>'Est End Date', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>false),
			array('name'=>'Description', 'type'=>'long text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			// Extra Tab
			array('name'=>'Estimating', 'type'=>'page_split', 'required'=>true, 'extra'=>true, 'visible'=>false),
			array('name'=>'GC Job No', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			// array('name'=>'GC Estimator','type'=>'crm_contact', 'param'=>array('field_type'=>'select','crits'=>array('Apps_ProjectsCommon','projects_contact_crits')), 'required'=>false, 'extra'=>true, 'visible'=>false),
			array('name'=>'GC Estimator','type'=>'crm_contact', 'param'=>array('field_type'=>'select', 'crits'=>array('Apps_ProjectsCommon','projects_contacts_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>true, 'visible'=>true),
			
			array('name'=>'Bid Invitation Recvd', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Bid Due Date', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Drawings Recvd', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Matl Pricing Obtained', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Walk Thru', 'type'=>'checkbox', 'required'=>false, 'extra'=>true, 'visible'=>false),
			array('name'=>'Walk Thru Date', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Proposal Submitted', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Estimate Amount', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'FollowUp Date', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Award Date', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),

			// Job details tab
			array('name'=>'Job Details', 'type'=>'page_split', 'required'=>true, 'extra'=>true, 'visible'=>false),

			array('name'=>'GC Project Manager','type'=>'crm_contact', 'param'=>array('field_type'=>'select', 'crits'=>array('Apps_ProjectsCommon','projects_contacts_pm_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>true, 'visible'=>false),
			array('name'=>'GC Supt','type'=>'crm_contact', 'param'=>array('field_type'=>'select', 'crits'=>array('Apps_ProjectsCommon','projects_contacts_supt_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>true, 'visible'=>false),

			array('name'=>'ZSI Roadman','type'=>'crm_contact', 'param'=>array('field_type'=>'select', 'crits'=>array('Apps_ProjectsCommon','projects_employees_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>true),
			array('name'=>'ZSI Foreman','type'=>'crm_contact', 'param'=>array('field_type'=>'select', 'crits'=>array('Apps_ProjectsCommon','projects_employees_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>true),
			array('name'=>'Painting', 'type'=>'checkbox', 'required'=>false, 'extra'=>true, 'visible'=>false),
			array('name'=>'Wallcovering', 'type'=>'checkbox', 'required'=>false, 'extra'=>true, 'visible'=>false),
			array('name'=>'Acoustics', 'type'=>'checkbox', 'required'=>false, 'extra'=>true, 'visible'=>false),

			array('name'=>'Submittal Package Sent', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'MSDS Product Data', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Samples Ordered', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Samples Received', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Samples Sent', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Samples Approved', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Finish Schedule Recvd', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Scope of Work Completed', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Job Start Review', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Pre Job Visit', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			array('name'=>'Job Conditions', 'type'=>'checkbox', 'required'=>false, 'extra'=>true, 'visible'=>false),

			// Admin tab
			array('name'=>'Admin', 'type'=>'page_split', 'required'=>true, 'extra'=>true, 'visible'=>false),
			array('name'=>'Contract Recvd', 'type'=>'checkbox', 'required'=>false, 'extra'=>true, 'visible'=>false),
			array('name'=>'Bonding Required', 'type'=>'checkbox', 'required'=>false, 'extra'=>true, 'visible'=>false),
			array('name'=>'Proposal to Acctg', 'type'=>'checkbox', 'required'=>false, 'extra'=>true, 'visible'=>false),
			array('name'=>'Insurance Cert Req', 'type'=>'checkbox', 'required'=>false, 'extra'=>true, 'visible'=>false),
			array('name'=>'Warranty', 'type'=>'checkbox', 'required'=>false, 'extra'=>true, 'visible'=>false),
			array('name'=>'P&L per Job', 'type'=>'checkbox', 'required'=>false, 'extra'=>true, 'visible'=>false),
			array('name'=>'Time for Job', 'type'=>'checkbox', 'required'=>false, 'extra'=>true, 'visible'=>false),
			array('name'=>'Bonus', 'type'=>'checkbox', 'required'=>false, 'extra'=>true, 'visible'=>false),
			array('name'=>'Paid in Full', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>true),
			
			// Accounting Tab
			array('name'=>'Accounting', 'type'=>'page_split', 'required'=>true, 'param'=>'3', 'extra'=>true, 'visible'=>false),
			array('name'=>'Paint Est Labor', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Paint Est LB', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Paint Est Material', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Paint Est Mandays', 'type'=>'integer', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
						
			array('name'=>'WC Est Labor', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'WC Est LB', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'WC Est Material', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'WC Est Mandays', 'type'=>'integer', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			
			array('name'=>'Acoust Est Labor', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Acoust Est LB', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Acoust Est Material', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Acoust Est Mandays', 'type'=>'integer', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			
			array('name'=>'Equipment Est Cost', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Misc Est Cost', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Misc Est Manhours', 'type'=>'integer', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),

			// Actual Cost
			array('name'=>'Paint Act Labor', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Paint Act LB', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Paint Act Material', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Paint Act Mandays', 'type'=>'integer', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
						
			array('name'=>'WC Act Labor', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'WC Act LB', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'WC Act Material', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'WC Act Mandays', 'type'=>'integer', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			
			array('name'=>'Acoust Act Labor', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Acoust Act LB', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Acoust Act Material', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Acoust Act Mandays', 'type'=>'integer', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			
			array('name'=>'Equipment Act Cost', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Misc Act Cost', 'type'=>'currency', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
			array('name'=>'Misc Act Manhours', 'type'=>'integer', 'required'=>false, 'param'=>'64', 'extra'=>true, 'visible'=>false),
		);

		Utils_RecordBrowserCommon::install_new_recordset('projects', $fields);
		//Utils_RecordBrowserCommon::set_tpl('contact', Base_ThemeCommon::get_template_filename('CRM/Contacts', 'Contact'));
		//Utils_RecordBrowserCommon::set_processing_method('projects', array('Apps_ProjectsCommon', 'submit_project'));
		Utils_RecordBrowserCommon::new_filter('projects', 'Company Name');
		Utils_RecordBrowserCommon::new_filter('projects', 'Status');
		
		Utils_RecordBrowserCommon::set_quickjump('projects', 'Project Name');
		Utils_RecordBrowserCommon::set_favorites('projects', true);
		Utils_RecordBrowserCommon::set_recent('projects', 15);
		Utils_RecordBrowserCommon::set_caption('projects', 'Projects');
		Utils_RecordBrowserCommon::set_icon('projects', Base_ThemeCommon::get_template_filename('Apps/Projects', 'icon.png'));
		Utils_RecordBrowserCommon::set_access_callback('projects', 'Apps_ProjectsCommon', 'access_projects');
		
// ************ addons ************** //
		Utils_RecordBrowserCommon::new_addon('projects', 'Apps/Projects', 'project_attachment_addon', 'Notes');
		Utils_RecordBrowserCommon::new_addon('company', 'Apps/Projects', 'company_projects_addon', 'Projects');

// ************ other ************** //	
		Utils_CommonDataCommon::new_array('Project_Status',array('itb_received'=>'ITB Received','proposal_submited'=>'Proposal Submitted','job_canceled'=>'Job Canceled','job_lost'=>'Job Lost','job_awarded'=>'Job Awarded','on_hold'=>'On Hold','in_progress'=>'In Progress','completed_unpaid'=>'Completed Unpaid','paid'=>'Paid'));
		Utils_CommonDataCommon::new_array('Job_Type',array('Commercial','Residential','Maintenance'));
		Utils_CommonDataCommon::extend_array('Companies_Groups',array('gc'=>'General Contractor','res'=>'Residential'));
		
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
		Utils_RecordBrowserCommon::delete_addon('company', 'Apps/Projects', 'company_projects_addon');
		Utils_RecordBrowserCommon::uninstall_recordset('projects');
		Utils_CommonDataCommon::remove('Project_Status');
		// Utils_AttachmentCommon::persistent_mass_delete(null,'Apps/Projects/');
		return true;
	}
	
	public function version() {
		return array("0.3");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base','version'=>0),
			array('name'=>'Utils/ChainedSelect', 'version'=>0), 
			array('name'=>'CRM/Contacts','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Projects Manager for ZSI Painting',
			'Author'=>'jtylek@telaxus.com',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>