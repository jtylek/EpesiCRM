<?php
/**
 * Projects Manager - Equipment
 * @author jtylek@telaxus.com
 * @copyright jtylek@telaxus.com
 * @license SPL
 * @version 0.3
 * @package apps-projects
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Projects_EquipmentInstall extends ModuleInstall {

	public function install() {
		
		$fields = array(
			array('name'=>'Lift Eq No', 'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('Apps_Projects_EquipmentCommon', 'equipment_callback')),
			array('name'=>'Requested by','type'=>'crm_contact', 'param'=>array('field_type'=>'select', 'crits'=>array('Apps_ProjectsCommon','projects_employees_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>true, 'extra'=>false, 'visible'=>true),
			array('name'=>'Project Name', 'type'=>'select','param'=>array('projects'=>'Project Name'), 'required'=>true, 'extra'=>false, 'visible'=>true, 'display_callback'=>array('Apps_Projects_EquipmentCommon', 'proj_name_callback')),
			// Default date needed to be set to today.
			array('name'=>'Date Needed', 'type'=>'date', 'required'=>true, 'param'=>64, 'extra'=>false, 'visible'=>true),
			array('name'=>'Est Return Date', 'type'=>'date', 'required'=>true, 'param'=>64, 'extra'=>false, 'visible'=>false),
			array('name'=>'Size and Type', 'type'=>'text', 'required'=>false, 'param'=>'64', 'extra'=>false),
			array('name'=>'Basket Size', 'type'=>'integer', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>false),
			array('name'=>'Interior', 'type'=>'checkbox', 'required'=>false, 'extra'=>false, 'visible'=>false),
			array('name'=>'Exterior', 'type'=>'checkbox', 'required'=>false, 'extra'=>false, 'visible'=>false),
			array('name'=>'Wax Coating', 'type'=>'checkbox', 'required'=>false, 'extra'=>false, 'visible'=>false),
			array('name'=>'Powered by', 'type'=>'commondata', 'required'=>true, 'visible'=>false, 'param'=>'Equipment_Power_Type', 'extra'=>false),
			array('name'=>'Notes', 'type'=>'long text', 'required'=>false, 'param'=>'254', 'extra'=>false),
			array('name'=>'Delivered', 'type'=>'checkbox', 'required'=>false, 'extra'=>false, 'visible'=>true),
			array('name'=>'Delivery Date', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>false, 'visible'=>true),
			array('name'=>'Returned', 'type'=>'checkbox', 'required'=>false, 'extra'=>false, 'visible'=>true),
			array('name'=>'Returned Date', 'type'=>'date', 'required'=>false, 'param'=>64, 'extra'=>false, 'visible'=>false)
		);
		Utils_RecordBrowserCommon::install_new_recordset('equipment', $fields);
		Utils_RecordBrowserCommon::set_recent('equipment', 15);
		Utils_RecordBrowserCommon::set_caption('equipment', 'Equipment');
		Utils_RecordBrowserCommon::set_icon('equipment', Base_ThemeCommon::get_template_filename('Apps/Projects', 'icon.png'));
		
// ************ addons ************** //
		//	Parameters: ('table','ModuleLocation','function','Label');
		Utils_RecordBrowserCommon::new_addon('projects', 'Apps/Projects/Equipment', 'project_equipment_addon', 'Equipment');
		Utils_RecordBrowserCommon::new_addon('equipment', 'Apps/Projects/Equipment', 'equipment_attachment_addon', 'Notes');
//
// ************ other ************** //	
		Utils_CommonDataCommon::new_array('Equipment_Power_Type',array('gas'=>'Gas','electr'=>'Electric'));
		Utils_RecordBrowserCommon::set_icon('equipment', Base_ThemeCommon::get_template_filename('Apps/Projects', 'icon.png'));
		Utils_RecordBrowserCommon::set_favorites('equipment', true);
		Utils_RecordBrowserCommon::new_filter('equipment', 'Project Name');

		return true;
	}
	
	public function uninstall() {
		//Base_ThemeCommon::uninstall_default_theme('Apps/Projects');
		Utils_RecordBrowserCommon::delete_addon('projects', 'Apps/Projects/Equipment', 'project_equipment_addon');
		Utils_RecordBrowserCommon::delete_addon('equipment', 'Apps/Projects/ChangeOrders', 'equipment_attachment_addon');
		Utils_RecordBrowserCommon::uninstall_recordset('equipment');
		Utils_CommonDataCommon::remove('Equipment_Power_Type');
		Utils_AttachmentCommon::persistent_mass_delete(null,'Apps/Projects/Equipment');
		return true;
	}
	
	public function version() {
		return array("0.3");
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
			'Description'=>'Projects Manager for ZSI Painting - Equipment',
			'Author'=>'jtylek@telaxus.com',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>